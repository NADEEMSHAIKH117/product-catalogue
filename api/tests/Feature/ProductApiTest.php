<?php

namespace Tests\Feature;

use App\Catalogue\Contracts\ProductRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    public function test_featured_returns_the_curated_products_in_configured_order(): void
    {
        $this->fakeStoreReturnsProducts();

        $this->getJson('/api/v1/products/featured')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.*.id', [3, 5, 11, 12, 18]);
    }

    public function test_featured_skips_ids_missing_from_the_source(): void
    {
        $this->fakeStoreReturnsProducts();
        config(['catalogue.featured_ids' => [3, 999, 5]]);

        $this->getJson('/api/v1/products/featured')
            ->assertOk()
            ->assertJsonPath('data.*.id', [3, 5]);
    }

    public function test_product_uses_our_contract_not_the_upstream_shape(): void
    {
        $this->fakeStoreReturnsProducts();

        $this->getJson('/api/v1/products/3')
            ->assertOk()
            ->assertExactJsonStructure([
                'data' => [
                    'id', 'title', 'description', 'price', 'image_url',
                    'category' => ['slug', 'name'],
                    'rating' => ['average', 'count'],
                ],
            ])
            ->assertJsonPath('data.title', 'Mens Cotton Jacket')
            ->assertJsonPath('data.category.slug', 'mens-clothing')
            ->assertJsonPath('data.rating.average', 4.7);
    }

    public function test_unknown_product_returns_404_json(): void
    {
        $this->fakeStoreReturnsProducts();

        $this->getJson('/api/v1/products/999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Product not found.');
    }

    public function test_search_only_returns_products_matching_the_term(): void
    {
        $this->fakeStoreReturnsProducts();

        $response = $this->getJson('/api/v1/products?search=SSD')->assertOk();

        $titles = array_column($response->json('data'), 'title');
        $this->assertNotEmpty($titles);
        foreach ($response->json('data') as $product) {
            $this->assertStringContainsStringIgnoringCase(
                'ssd',
                $product['title'].' '.$product['description'].' '.$product['category']['name'],
            );
        }
        // Nothing outside electronics mentions SSD.
        $this->assertSame(['electronics'], array_values(array_unique(array_column(array_column($response->json('data'), 'category'), 'slug'))));
    }

    public function test_search_requires_every_word_to_match(): void
    {
        $this->fakeStoreReturnsProducts();

        $this->getJson('/api/v1/products?search=mens%20jacket')
            ->assertOk()
            ->assertJsonPath('data.*.id', [3]);
    }

    public function test_search_matches_from_the_start_of_words_only(): void
    {
        $this->fakeStoreReturnsProducts();

        // "men" must not match "women's clothing"...
        $ids = array_column($this->getJson('/api/v1/products?search=men')->assertOk()->json('data'), 'id');
        $this->assertNotEmpty($ids);
        $this->assertEmpty(array_intersect($ids, [15, 16, 17, 18, 19, 20]), 'women\'s clothing matched "men"');

        // ...but a prefix still matches: "jack" finds jackets.
        $this->getJson('/api/v1/products?search=jack')->assertOk()->assertJsonPath('data.0.id', 3);
    }

    public function test_search_with_no_matches_returns_an_empty_list(): void
    {
        $this->fakeStoreReturnsProducts();

        $this->getJson('/api/v1/products?search=zzzz-no-such-thing')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_search_term_is_length_limited(): void
    {
        $this->getJson('/api/v1/products?search='.str_repeat('a', 101))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('search');
    }

    public function test_out_of_range_product_id_is_a_404_not_a_server_error(): void
    {
        $this->getJson('/api/v1/products/99999999999999999999')->assertNotFound();
        $this->getJson('/api/v1/products/abc')->assertNotFound();
    }

    public function test_product_list_without_search_returns_everything(): void
    {
        $this->fakeStoreReturnsProducts();

        $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(20, 'data');
    }

    public function test_upstream_responses_are_cached_between_requests(): void
    {
        $this->fakeStoreReturnsProducts();

        $this->getJson('/api/v1/products/featured')->assertOk();
        $this->getJson('/api/v1/products/3')->assertOk();
        $this->getJson('/api/v1/products?search=ssd')->assertOk();

        Http::assertSentCount(1);
    }

    public function test_upstream_outage_returns_503_and_is_not_cached(): void
    {
        Sleep::fake();
        $upstreamDown = true;
        $products = json_decode(file_get_contents(resource_path('data/products.json')), true);
        // By-reference closure (not an arrow fn) so flipping $upstreamDown below takes effect.
        Http::fake(function () use (&$upstreamDown, $products) {
            return $upstreamDown ? Http::response('Bad gateway', 502) : Http::response($products);
        });

        $this->getJson('/api/v1/products/featured')
            ->assertStatus(503)
            ->assertJsonPath('message', 'The product catalogue is temporarily unavailable. Please try again shortly.');

        // Once Fake Store recovers, the next request succeeds: the failure was not cached.
        $upstreamDown = false;
        $this->app->forgetInstance(ProductRepository::class);

        $this->getJson('/api/v1/products/featured')->assertOk();
    }

    public function test_malformed_upstream_records_are_dropped(): void
    {
        Http::fake(['fakestore.test/products' => Http::response([
            ['id' => 1, 'title' => 'Valid', 'price' => 10, 'category' => 'electronics'],
            ['title' => 'No id'],
            'not even an object',
        ])]);

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('data.*.id', [1]);
    }

    public function test_an_empty_upstream_catalogue_is_an_outage_and_is_not_cached(): void
    {
        Sleep::fake();
        Http::fake(['fakestore.test/products' => Http::response([['title' => 'No id']])]);

        $this->getJson('/api/v1/products/featured')->assertStatus(503);
        $this->assertFalse(cache()->has('catalogue.fakestore.products'));
    }

    public function test_api_does_not_grant_cross_origin_access_by_default(): void
    {
        $this->fakeStoreReturnsProducts();

        $this->withHeaders(['Origin' => 'https://evil.example'])
            ->getJson('/api/v1/products/featured')
            ->assertOk()
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}

<?php

namespace Tests\Feature;

use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeStoreReturnsProducts();
    }

    public function test_lists_categories_with_url_safe_slugs(): void
    {
        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonPath('data', [
                ['slug' => 'mens-clothing', 'name' => "men's clothing"],
                ['slug' => 'jewelery', 'name' => 'jewelery'],
                ['slug' => 'electronics', 'name' => 'electronics'],
                ['slug' => 'womens-clothing', 'name' => "women's clothing"],
            ]);
    }

    public function test_curated_products_lead_the_top_rated_row_topped_up_by_rating(): void
    {
        // Men's clothing: 3 is curated; 2 (4.1) and 1 (3.9) are the best of the rest; 4 (2.1) is left over.
        $this->getJson('/api/v1/categories/mens-clothing/products')
            ->assertOk()
            ->assertJsonPath('data.category.slug', 'mens-clothing')
            ->assertJsonPath('data.top_rated.*.id', [3, 2, 1])
            ->assertJsonPath('data.others.*.id', [4]);
    }

    public function test_every_product_in_the_category_appears_exactly_once(): void
    {
        $data = $this->getJson('/api/v1/categories/electronics/products')->assertOk()->json('data');

        $ids = [...array_column($data['top_rated'], 'id'), ...array_column($data['others'], 'id')];
        sort($ids);

        $this->assertSame([9, 10, 11, 12, 13, 14], $ids);
    }

    public function test_curated_order_is_kept_even_if_ratings_disagree(): void
    {
        // Owner's choice wins over customer rating: 13 (2.9) is curated ahead of 11 (4.8).
        config(['catalogue.featured_ids' => [13, 11]]);

        $this->getJson('/api/v1/categories/electronics/products')
            ->assertOk()
            ->assertJsonPath('data.top_rated.0.id', 13)
            ->assertJsonPath('data.top_rated.1.id', 11);
    }

    public function test_uncurated_category_falls_back_to_customer_rating(): void
    {
        config(['catalogue.featured_ids' => []]);

        // Jewelery by rating: 5 (4.6), 6 (3.9), 7 (3.0); 8 (1.9) is left over.
        $this->getJson('/api/v1/categories/jewelery/products')
            ->assertOk()
            ->assertJsonPath('data.top_rated.*.id', [5, 6, 7])
            ->assertJsonPath('data.others.*.id', [8]);
    }

    public function test_top_rated_row_size_is_configurable(): void
    {
        config(['catalogue.category_top_rated_count' => 0, 'catalogue.featured_ids' => []]);

        $this->getJson('/api/v1/categories/jewelery/products')
            ->assertOk()
            ->assertJsonPath('data.category.slug', 'jewelery')
            ->assertJsonCount(0, 'data.top_rated')
            ->assertJsonCount(4, 'data.others');
    }

    public function test_unknown_category_returns_404(): void
    {
        $this->getJson('/api/v1/categories/garden-furniture/products')->assertNotFound();
    }
}

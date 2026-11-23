<?php

namespace Tests\Feature;

use App\Catalogue\Contracts\ProductRepository;
use App\Catalogue\Repositories\FixtureProductRepository;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Swapping the data source is a config change: the same API works with no network at all.
 */
class FixtureSourceTest extends TestCase
{
    public function test_api_serves_products_from_the_fixture_source_without_any_http(): void
    {
        config(['catalogue.source' => 'fixture']);

        $this->assertInstanceOf(FixtureProductRepository::class, $this->app->make(ProductRepository::class));

        $this->getJson('/api/v1/products/featured')
            ->assertOk()
            ->assertJsonPath('data.*.id', [3, 5, 11, 12, 18]);

        Http::assertNothingSent();
    }

    public function test_unknown_source_fails_loudly_at_boot(): void
    {
        config(['catalogue.source' => 'magic']);

        $this->expectException(\InvalidArgumentException::class);
        $this->app->make(ProductRepository::class);
    }
}

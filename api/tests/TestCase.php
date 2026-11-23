<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests must never hit the real Fake Store API.
        Http::preventStrayRequests();

        config([
            'catalogue.source' => 'fakestore',
            'catalogue.fakestore.base_url' => 'https://fakestore.test',
            'catalogue.featured_ids' => [3, 5, 11, 12, 18],
            'catalogue.category_top_rated_count' => 3,
            'catalogue.sales_inbox' => 'sales@example.test',
        ]);
    }

    /** Fake Store responds with the bundled snapshot of its 20 products. */
    protected function fakeStoreReturnsProducts(): void
    {
        Http::fake([
            'fakestore.test/products' => Http::response(
                json_decode(file_get_contents(resource_path('data/products.json')), true),
            ),
        ]);
    }
}

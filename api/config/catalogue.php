<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Product data source
    |--------------------------------------------------------------------------
    |
    | Which ProductRepository implementation to use. "fakestore" calls the
    | Fake Store API; "fixture" reads a JSON file bundled with the app (useful
    | offline, in tests, or if Fake Store is down). Adding a new source means
    | adding a repository class and a case in CatalogueServiceProvider.
    |
    */

    'source' => env('PRODUCT_SOURCE', 'fakestore'),

    'fakestore' => [
        'base_url' => env('FAKESTORE_BASE_URL', 'https://fakestoreapi.com'),
        'timeout' => (int) env('FAKESTORE_TIMEOUT', 5),
        'cache_ttl' => (int) env('PRODUCT_CACHE_TTL', 600),
    ],

    'fixture_path' => resource_path('data/products.json'),

    /*
    |--------------------------------------------------------------------------
    | Top rated products
    |--------------------------------------------------------------------------
    |
    | "Top rated" is curated by the owner: an ordered list of product IDs.
    | The home page shows these. Category pages show the curated products in
    | that category first, topped up by customer rating to the count below.
    |
    */

    'featured_ids' => array_values(array_map(
        'intval',
        array_filter(explode(',', (string) env('FEATURED_PRODUCT_IDS', '3,5,11,12,18')), 'is_numeric'),
    )),

    'category_top_rated_count' => (int) env('CATEGORY_TOP_RATED_COUNT', 3),

    /*
    |--------------------------------------------------------------------------
    | Enquiries
    |--------------------------------------------------------------------------
    */

    'sales_inbox' => env('SALES_INBOX_ADDRESS', 'sales@example.com'),

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | Proxies allowed to set X-Forwarded-For (the Vite dev proxy locally, the
    | load balancer in production), so rate limits see the real client IP.
    | Never use '*': any client could then spoof its IP and dodge the limits.
    |
    */

    'trusted_proxies' => array_values(array_filter(explode(',', (string) env('TRUSTED_PROXIES', '')))),

];

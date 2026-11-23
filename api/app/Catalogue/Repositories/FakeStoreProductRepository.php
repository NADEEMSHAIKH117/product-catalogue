<?php

namespace App\Catalogue\Repositories;

use App\Catalogue\Exceptions\ProductSourceUnavailable;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\RequestException;

class FakeStoreProductRepository extends InMemoryProductRepository
{
    private const CACHE_KEY = 'catalogue.fakestore.products';

    /** Per-request memo so one request never hits the cache store twice. */
    private ?array $products = null;

    public function __construct(
        private readonly Http $http,
        private readonly Cache $cache,
        private readonly string $baseUrl,
        private readonly int $timeoutSeconds,
        private readonly int $cacheTtlSeconds,
    ) {}

    public function all(): array
    {
        // Cache the raw payload, not Product objects, so changing the Product
        // class never collides with stale serialized objects. Only successful
        // responses are cached: if the fetch throws, nothing is stored.
        return $this->products ??= array_map(
            $this->mapProduct(...),
            $this->cache->remember(self::CACHE_KEY, $this->cacheTtlSeconds, fn () => $this->fetchPayload()),
        );
    }

    /** @return list<array<string, mixed>> */
    private function fetchPayload(): array
    {
        try {
            $payload = $this->http
                ->baseUrl($this->baseUrl)
                ->acceptJson()
                ->timeout($this->timeoutSeconds)
                ->retry(2, 200)
                ->get('/products')
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException $e) {
            throw new ProductSourceUnavailable('Fake Store request failed: '.$e->getMessage(), previous: $e);
        }

        if (! is_array($payload) || ! array_is_list($payload)) {
            throw new ProductSourceUnavailable('Fake Store returned an unexpected payload.');
        }

        // Drop records we can't identify rather than failing the whole catalogue.
        return array_values(array_filter(
            $payload,
            fn ($item) => is_array($item) && isset($item['id'], $item['title']),
        ));
    }
}

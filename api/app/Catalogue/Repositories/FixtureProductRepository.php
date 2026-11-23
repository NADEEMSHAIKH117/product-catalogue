<?php

namespace App\Catalogue\Repositories;

use App\Catalogue\Exceptions\ProductSourceUnavailable;

/**
 * Reads products from a bundled JSON file (a snapshot of Fake Store).
 * Lets the app run with no network and proves the source is swappable.
 */
class FixtureProductRepository extends InMemoryProductRepository
{
    private ?array $products = null;

    public function __construct(private readonly string $path) {}

    public function all(): array
    {
        if ($this->products !== null) {
            return $this->products;
        }

        $payload = is_readable($this->path)
            ? json_decode((string) file_get_contents($this->path), true)
            : null;

        if (! is_array($payload) || ! array_is_list($payload)) {
            throw new ProductSourceUnavailable("Product fixture missing or invalid: {$this->path}");
        }

        return $this->products = array_map($this->mapProduct(...), $payload);
    }
}

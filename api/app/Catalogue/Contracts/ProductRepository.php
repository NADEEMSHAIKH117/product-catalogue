<?php

namespace App\Catalogue\Contracts;

use App\Catalogue\Category;
use App\Catalogue\Exceptions\ProductSourceUnavailable;
use App\Catalogue\Product;

/**
 * The boundary between the app and wherever product data lives.
 *
 * All methods may throw ProductSourceUnavailable when the upstream source
 * cannot be reached; callers should not catch upstream-specific exceptions.
 */
interface ProductRepository
{
    /**
     * @return list<Product>
     *
     * @throws ProductSourceUnavailable
     */
    public function all(): array;

    /** @throws ProductSourceUnavailable */
    public function find(int $id): ?Product;

    /**
     * @return list<Category>
     *
     * @throws ProductSourceUnavailable
     */
    public function categories(): array;

    /**
     * @return list<Product>
     *
     * @throws ProductSourceUnavailable
     */
    public function inCategory(string $slug): array;

    /**
     * Products whose title, description or category contain a word starting with each word of the term (case-insensitive).
     *
     * @return list<Product>
     *
     * @throws ProductSourceUnavailable
     */
    public function search(string $term): array;
}

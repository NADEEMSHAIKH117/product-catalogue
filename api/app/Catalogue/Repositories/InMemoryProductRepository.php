<?php

namespace App\Catalogue\Repositories;

use App\Catalogue\Category;
use App\Catalogue\Contracts\ProductRepository;
use App\Catalogue\Product;
use Illuminate\Support\Str;

/**
 * Base for sources small enough to load whole (Fake Store has 20 products).
 * Lookups, category filtering and search run over all(). A source with its
 * own search or paging (a database, a search index) should implement
 * ProductRepository directly and push these queries down instead.
 */
abstract class InMemoryProductRepository implements ProductRepository
{
    public function find(int $id): ?Product
    {
        foreach ($this->all() as $product) {
            if ($product->id === $id) {
                return $product;
            }
        }

        return null;
    }

    public function categories(): array
    {
        $categories = [];

        foreach ($this->all() as $product) {
            $categories[$product->category->slug] ??= $product->category;
        }

        return array_values($categories);
    }

    public function inCategory(string $slug): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (Product $product) => $product->category->slug === $slug,
        ));
    }

    public function search(string $term): array
    {
        $words = preg_split('/\s+/', Str::lower(trim($term)), -1, PREG_SPLIT_NO_EMPTY);

        if ($words === []) {
            return [];
        }

        return array_values(array_filter($this->all(), function (Product $product) use ($words) {
            $haystack = Str::lower("{$product->title} {$product->description} {$product->category->name}");

            foreach ($words as $word) {
                if (! str_contains($haystack, $word)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * Map one raw Fake Store-shaped record into a Product.
     *
     * @param  array<string, mixed>  $item
     */
    protected function mapProduct(array $item): Product
    {
        return new Product(
            id: (int) $item['id'],
            title: (string) $item['title'],
            description: (string) ($item['description'] ?? ''),
            price: (float) ($item['price'] ?? 0),
            category: Category::fromName((string) ($item['category'] ?? 'uncategorised')),
            imageUrl: isset($item['image']) ? (string) $item['image'] : null,
            rating: isset($item['rating']['rate']) ? (float) $item['rating']['rate'] : null,
            ratingCount: (int) ($item['rating']['count'] ?? 0),
        );
    }
}

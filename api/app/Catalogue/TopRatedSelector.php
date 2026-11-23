<?php

namespace App\Catalogue;

use App\Catalogue\Contracts\ProductRepository;
use Illuminate\Support\Facades\Log;

/**
 * The single definition of "top rated" used by every page.
 *
 * Top rated = the owner's curated list (config catalogue.featured_ids).
 * On a category page, curated products in that category come first and the
 * row is topped up with the category's best customer-rated products, so a
 * category the owner hasn't curated still has a top rated row.
 */
class TopRatedSelector
{
    /**
     * @param  list<int>  $featuredIds
     */
    public function __construct(
        private readonly ProductRepository $products,
        private readonly array $featuredIds,
        private readonly int $categoryTopRatedCount,
    ) {}

    /**
     * The curated products, in the order the owner listed them.
     *
     * @return list<Product>
     */
    public function featured(): array
    {
        $featured = [];

        foreach ($this->featuredIds as $id) {
            $product = $this->products->find($id);

            if ($product === null) {
                // A stale ID in config shouldn't take the home page down.
                Log::warning('Featured product not found in source', ['id' => $id]);

                continue;
            }

            $featured[] = $product;
        }

        return $featured;
    }

    /**
     * Split a category into its top rated row and everything else.
     * Returns null when the category doesn't exist.
     *
     * @return array{category: Category, top_rated: list<Product>, others: list<Product>}|null
     */
    public function forCategory(string $slug): ?array
    {
        $products = $this->products->inCategory($slug);

        if ($products === []) {
            return null;
        }

        $curated = array_values(array_filter(
            array_map(
                fn (int $id) => $this->firstWithId($products, $id),
                $this->featuredIds,
            ),
        ));

        $curatedIds = array_map(fn (Product $p) => $p->id, $curated);
        $remaining = array_values(array_filter(
            $products,
            fn (Product $p) => ! in_array($p->id, $curatedIds, true),
        ));

        $byRating = $remaining;
        usort($byRating, fn (Product $a, Product $b) => ($b->rating ?? 0) <=> ($a->rating ?? 0));

        $topUp = array_slice($byRating, 0, max(0, $this->categoryTopRatedCount - count($curated)));
        $topRated = [...$curated, ...$topUp];
        $topRatedIds = array_map(fn (Product $p) => $p->id, $topRated);

        return [
            'category' => $products[0]->category,
            'top_rated' => $topRated,
            // "Others" keep the source's order so the row below reads as the plain catalogue.
            'others' => array_values(array_filter(
                $remaining,
                fn (Product $p) => ! in_array($p->id, $topRatedIds, true),
            )),
        ];
    }

    /** @param  list<Product>  $products */
    private function firstWithId(array $products, int $id): ?Product
    {
        foreach ($products as $product) {
            if ($product->id === $id) {
                return $product;
            }
        }

        return null;
    }
}

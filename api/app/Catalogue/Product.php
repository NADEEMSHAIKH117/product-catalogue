<?php

namespace App\Catalogue;

/**
 * Our own product model. Every data source maps into this shape, so nothing
 * outside a repository knows what the upstream payload looks like.
 */
final readonly class Product
{
    public function __construct(
        public int $id,
        public string $title,
        public string $description,
        public float $price,
        public Category $category,
        public ?string $imageUrl,
        public ?float $rating,
        public int $ratingCount,
    ) {}
}

<?php

namespace App\Catalogue;

use Illuminate\Support\Str;

final readonly class Category
{
    public function __construct(
        public string $slug,
        public string $name,
    ) {}

    /** Build a category from a display name, e.g. "men's clothing" -> "mens-clothing". */
    public static function fromName(string $name): self
    {
        return new self(Str::slug($name), $name);
    }
}

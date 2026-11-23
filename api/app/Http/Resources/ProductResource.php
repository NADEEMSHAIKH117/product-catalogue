<?php

namespace App\Http\Resources;

use App\Catalogue\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Our public API contract for a product. Deliberately independent of the
 * upstream shape (e.g. Fake Store's "image" and "rating.rate").
 *
 * @property Product $resource
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'price' => $this->resource->price,
            'category' => new CategoryResource($this->resource->category),
            'image_url' => $this->resource->imageUrl,
            'rating' => [
                'average' => $this->resource->rating,
                'count' => $this->resource->ratingCount,
            ],
        ];
    }
}

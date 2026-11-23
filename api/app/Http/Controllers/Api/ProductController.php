<?php

namespace App\Http\Controllers\Api;

use App\Catalogue\Contracts\ProductRepository;
use App\Catalogue\TopRatedSelector;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function __construct(private readonly ProductRepository $products) {}

    /** GET /products?search= — all products, or only those matching the search term. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $term = trim($validated['search'] ?? '');

        return ProductResource::collection(
            $term === '' ? $this->products->all() : $this->products->search($term),
        );
    }

    /** GET /products/featured — the owner's curated top rated products. */
    public function featured(TopRatedSelector $topRated): AnonymousResourceCollection
    {
        return ProductResource::collection($topRated->featured());
    }

    /** GET /products/{id} */
    public function show(int $id): ProductResource
    {
        $product = $this->products->find($id) ?? abort(404, 'Product not found.');

        return new ProductResource($product);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Catalogue\Contracts\ProductRepository;
use App\Catalogue\TopRatedSelector;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    /** GET /categories */
    public function index(ProductRepository $products): AnonymousResourceCollection
    {
        return CategoryResource::collection($products->categories());
    }

    /** GET /categories/{slug}/products — top rated row plus the rest of the category. */
    public function products(string $slug, TopRatedSelector $topRated): JsonResponse
    {
        $sections = $topRated->forCategory($slug) ?? abort(404, 'Category not found.');

        return response()->json([
            'data' => [
                'category' => new CategoryResource($sections['category']),
                'top_rated' => ProductResource::collection($sections['top_rated']),
                'others' => ProductResource::collection($sections['others']),
            ],
        ]);
    }
}

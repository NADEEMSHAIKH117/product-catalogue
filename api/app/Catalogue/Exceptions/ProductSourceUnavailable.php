<?php

namespace App\Catalogue\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Thrown when the product data source cannot be reached or returns garbage.
 * Rendered as a 503 so the client can show a "try again" state.
 */
class ProductSourceUnavailable extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'The product catalogue is temporarily unavailable. Please try again shortly.',
        ], 503);
    }
}

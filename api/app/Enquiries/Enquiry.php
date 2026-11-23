<?php

namespace App\Enquiries;

use App\Catalogue\Product;

/**
 * A customer's enquiry about a product. The product is resolved server-side
 * from its ID, never taken from client-supplied title/price.
 */
final readonly class Enquiry
{
    public function __construct(
        public Product $product,
        public string $name,
        public string $email,
        public ?string $phone,
        public ?string $message,
    ) {}
}

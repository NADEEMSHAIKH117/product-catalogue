<?php

namespace App\Http\Requests;

use App\Catalogue\Contracts\ProductRepository;
use App\Catalogue\Product;
use App\Enquiries\Enquiry;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreEnquiryRequest extends FormRequest
{
    private ?Product $product = null;

    public function authorize(): bool
    {
        // Public form: anyone may enquire. Abuse is limited by the throttle on the route.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', function (string $attribute, mixed $value, Closure $fail) {
                $this->product = app(ProductRepository::class)->find((int) $value);

                if ($this->product === null) {
                    $fail('The selected product does not exist.');
                }
            }],
            'name' => ['required', 'string', 'max:100'],
            // rfc,strict matches what the mailer accepts; plain 'email' lets through addresses
            // like jane(c)@example.com that then fail when building the message.
            'email' => ['required', 'string', 'email:rfc,strict', 'max:254'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]+$/'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function toEnquiry(): Enquiry
    {
        return new Enquiry(
            product: $this->product,
            name: trim($this->validated('name')),
            email: trim($this->validated('email')),
            phone: $this->validated('phone') ? trim($this->validated('phone')) : null,
            message: $this->validated('message') ? trim($this->validated('message')) : null,
        );
    }
}

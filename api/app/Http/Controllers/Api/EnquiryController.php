<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEnquiryRequest;
use App\Mail\ProductEnquiryMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class EnquiryController extends Controller
{
    /** POST /enquiries — email the sales inbox about a product. */
    public function store(StoreEnquiryRequest $request): JsonResponse
    {
        $enquiry = $request->toEnquiry();

        try {
            // Sent synchronously so the customer only sees "Thank you" once the
            // email has actually been handed to the mail server. See DECISIONS.md.
            Mail::to(config('catalogue.sales_inbox'))->send(new ProductEnquiryMail($enquiry));
        } catch (TransportExceptionInterface $e) {
            Log::error('Failed to send product enquiry', [
                'product_id' => $enquiry->product->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Sorry, we could not send your enquiry right now. Please try again shortly.',
            ], 503);
        }

        return response()->json([
            'message' => 'Thank you! Our sales team will be in touch soon.',
        ], 202);
    }
}

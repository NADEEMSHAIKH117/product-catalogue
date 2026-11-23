<?php

namespace Tests\Feature;

use App\Mail\ProductEnquiryMail;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EnquiryApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Each test gets a fresh array cache, so rate limit counters start at zero.
        $this->fakeStoreReturnsProducts();
    }

    protected function tearDown(): void
    {
        TrustProxies::flushState();
        parent::tearDown();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'product_id' => 3,
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+61 400 000 000',
            'message' => 'Do you have this in XL?',
        ], $overrides);
    }

    public function test_valid_enquiry_emails_the_sales_inbox_and_thanks_the_customer(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/enquiries', $this->validPayload())
            ->assertStatus(202)
            ->assertJsonPath('message', 'Thank you! Our sales team will be in touch soon.');

        Mail::assertSent(ProductEnquiryMail::class, function (ProductEnquiryMail $mail) {
            return $mail->hasTo('sales@example.test')
                && $mail->hasReplyTo('jane@example.com', 'Jane Doe')
                && $mail->hasSubject('New enquiry: Mens Cotton Jacket')
                && $mail->enquiry->product->id === 3
                && $mail->enquiry->message === 'Do you have this in XL?';
        });
    }

    public function test_product_details_come_from_the_source_not_the_client(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/enquiries', $this->validPayload([
            'title' => 'Free iPhone',
            'price' => 0,
        ]))->assertStatus(202);

        Mail::assertSent(ProductEnquiryMail::class, fn (ProductEnquiryMail $mail) => $mail->enquiry->product->title === 'Mens Cotton Jacket'
            && $mail->enquiry->product->price === 55.99);
    }

    public function test_phone_and_message_are_optional(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/enquiries', $this->validPayload(['phone' => null, 'message' => null]))
            ->assertStatus(202);

        Mail::assertSent(ProductEnquiryMail::class);
    }

    public function test_invalid_contact_details_are_rejected_and_no_email_is_sent(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/enquiries', $this->validPayload([
            'name' => '',
            'email' => 'not-an-email',
            'phone' => 'call me maybe',
            'message' => str_repeat('a', 2001),
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'phone', 'message']);

        Mail::assertNothingSent();
    }

    public function test_email_must_be_one_the_mailer_can_actually_send_to(): void
    {
        Mail::fake();

        // Passes RFC validation but Symfony Mime rejects it when building the message.
        $this->postJson('/api/v1/enquiries', $this->validPayload(['email' => 'jane(c)@example.com']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        Mail::assertNothingSent();
    }

    public function test_enquiry_for_unknown_product_is_rejected(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/enquiries', $this->validPayload(['product_id' => 999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_id');

        Mail::assertNothingSent();
    }

    public function test_enquiries_are_rate_limited_per_client(): void
    {
        Mail::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/enquiries', $this->validPayload())->assertStatus(202);
        }

        $this->postJson('/api/v1/enquiries', $this->validPayload())->assertStatus(429);
        Mail::assertSentCount(5);
    }

    public function test_rate_limit_uses_the_real_client_ip_behind_a_trusted_proxy(): void
    {
        Mail::fake();
        TrustProxies::at(['172.16.0.0/12']);
        $viaProxy = fn (string $clientIp) => $this->withServerVariables([
            'REMOTE_ADDR' => '172.18.0.5',
            'HTTP_X_FORWARDED_FOR' => $clientIp,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $viaProxy('203.0.113.1')->postJson('/api/v1/enquiries', $this->validPayload())->assertStatus(202);
        }
        $viaProxy('203.0.113.1')->postJson('/api/v1/enquiries', $this->validPayload())->assertStatus(429);

        // A different customer behind the same proxy is not blocked.
        $viaProxy('203.0.113.2')->postJson('/api/v1/enquiries', $this->validPayload())->assertStatus(202);
    }

    public function test_untrusted_clients_cannot_dodge_the_rate_limit_by_spoofing_forwarded_for(): void
    {
        Mail::fake();
        TrustProxies::at(['172.16.0.0/12']);

        for ($i = 0; $i < 6; $i++) {
            $response = $this->withServerVariables([
                'REMOTE_ADDR' => '198.51.100.9',
                'HTTP_X_FORWARDED_FOR' => "203.0.113.{$i}",
            ])->postJson('/api/v1/enquiries', $this->validPayload());
        }

        $response->assertStatus(429);
    }

    public function test_mail_server_failure_tells_the_customer_to_retry(): void
    {
        // A real SMTP transport pointed at a closed port: sending genuinely fails.
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 1,
            'mail.mailers.smtp.timeout' => 1,
        ]);

        $this->postJson('/api/v1/enquiries', $this->validPayload())
            ->assertStatus(503)
            ->assertJsonPath('message', 'Sorry, we could not send your enquiry right now. Please try again shortly.');
    }

    public function test_email_body_escapes_customer_input(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/enquiries', $this->validPayload([
            'name' => 'Jane <script>alert(1)</script>',
        ]))->assertStatus(202);

        Mail::assertSent(ProductEnquiryMail::class, function (ProductEnquiryMail $mail) {
            $html = $mail->render();

            return str_contains($html, 'Jane &lt;script&gt;')
                && ! str_contains($html, '<script>');
        });
    }

    public function test_plain_text_email_is_not_html_escaped(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/enquiries', $this->validPayload(['name' => "Pat O'Brien"]))->assertStatus(202);

        Mail::assertSent(ProductEnquiryMail::class, function (ProductEnquiryMail $mail) {
            $mail->assertSeeInText("Name: Pat O'Brien");
            $mail->assertSeeInText("Category: men's clothing");

            return true;
        });
    }
}

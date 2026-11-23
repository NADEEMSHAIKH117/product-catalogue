{{-- All customer input is escaped by {{ }}. Keep it that way. --}}
<!DOCTYPE html>
<html lang="en">
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h2 style="margin-bottom: 4px;">New product enquiry</h2>
    <p style="margin-top: 0; color: #6b7280;">Reply to this email to respond to the customer directly.</p>

    <h3>Product</h3>
    <p>
        <strong>{{ $enquiry->product->title }}</strong> (ID {{ $enquiry->product->id }})<br>
        {{ $enquiry->product->category->name }} &middot; ${{ number_format($enquiry->product->price, 2) }}
    </p>

    <h3>Customer</h3>
    <p>
        Name: {{ $enquiry->name }}<br>
        Email: {{ $enquiry->email }}<br>
        Phone: {{ $enquiry->phone ?? 'Not provided' }}
    </p>

    <h3>Message</h3>
    <p style="white-space: pre-line;">{{ $enquiry->message ?? 'No message provided.' }}</p>
</body>
</html>

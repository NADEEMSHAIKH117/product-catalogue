New product enquiry

Product: {{ $enquiry->product->title }} (ID {{ $enquiry->product->id }})
Category: {{ $enquiry->product->category->name }}
Price: ${{ number_format($enquiry->product->price, 2) }}

Name: {{ $enquiry->name }}
Email: {{ $enquiry->email }}
Phone: {{ $enquiry->phone ?? 'Not provided' }}

Message:
{{ $enquiry->message ?? 'No message provided.' }}

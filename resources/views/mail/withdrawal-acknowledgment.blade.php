Who's Chanting — withdrawal request

{{ $forSupport ? 'A customer submitted the following withdrawal declaration.' : 'We received your withdrawal declaration. Keep this email as your acknowledgment.' }}

Request reference: {{ $details['reference'] }}
Received at: {{ $details['received_at'] }} (UTC)
Name: {{ $details['name'] }}
Email: {{ $details['email'] }}
Purchase reference: {{ $details['order_reference'] }}

Declaration:
{{ $details['declaration'] }}

@if ($details['message'])
Additional information:
{{ $details['message'] }}
@endif

This confirms receipt of the request, not that a refund has already been issued. We will review the purchase and respond about the next steps.

Support: {{ config('legal.support_email') }}
Refunds and cancellation: {{ url('/refunds') }}

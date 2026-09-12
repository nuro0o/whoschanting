WHO’S CHANTING? — PURCHASE CONFIRMATION

Thank you for supporting the village.
A little more you. A little more light in the village.

YOUR PURCHASE
{!! $receipt['name'] !!}
Amount paid: {!! $receipt['amount_label'] !!}
Payment confirmed: {!! $receipt['paid_at'] !!}
Order reference: {!! $receipt['order_id'] !!}

INCLUDED IN YOUR PACK
@foreach ($receipt['items'] as $item)
- {!! $item !!}
@endforeach

Keep this email as your purchase confirmation. Your purchase history shows your pack’s contents, match use and refund options.
View your purchases: {!! $receipt['purchases_url'] !!}

YOUR REFUND INFORMATION
{!! $receipt['policy_text'] !!}

You can always contact us about faulty or undelivered content. Repeated refunded purchases may require support review before buying again.
Refunds and purchase support: {!! $receipt['refunds_url'] !!}

YOUR DIGITAL CONTENT CHOICE
@if ($receipt['consent_text'])
{!! $receipt['consent_text'] !!}
@if ($receipt['consent_accepted_at'])
Accepted at checkout: {!! $receipt['consent_accepted_at'] !!}
@endif
@else
No immediate-supply consent was recorded for this purchase. Your existing statutory rights are preserved. This confirmation does not retrospectively waive them.
@endif

A question about your purchase? Email {!! $receipt['support_email'] !!} and include your order reference.

@if ($receipt['operator_name'])
{!! $receipt['operator_name'] !!}
@endif
@if ($receipt['business_address'])
{!! $receipt['business_address'] !!}
@endif
@if ($receipt['registration_number'])
Registration number: {!! $receipt['registration_number'] !!}
@endif

Terms of Service: {!! $receipt['terms_url'] !!}
Privacy Notice: {!! $receipt['privacy_url'] !!}
Refunds: {!! $receipt['refunds_url'] !!}

Good company. A village worth coming back to.

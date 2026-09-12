<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Your {{ $receipt['name'] }} purchase</title><style>@media screen and (max-width:480px) { .receipt-art { display:none !important; } .receipt-header-copy { padding-right:0 !important; } }</style></head>
<body style="margin:0;padding:0;background-color:#eae5d8;color:#263a38;font-family:Arial,Helvetica,sans-serif;">
    <div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">Your {{ $receipt['name'] }} purchase confirmation, contents and refund information.</div>
    <div role="main" aria-label="Purchase confirmation">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#eae5d8;"><tr><td align="center" style="padding:24px 12px;">
        <table role="presentation" width="620" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:620px;background-color:#f8f4e9;border:1px solid #cbc9b6;">
            <tr><td style="padding:24px 28px;background-color:#263f36;border-top:4px solid #b09962;color:#eee8d6;">
                <p style="margin:0 0 18px;color:#dec995;font-size:11px;letter-spacing:2px;">WHO’S CHANTING? &nbsp; / &nbsp; PURCHASE CONFIRMATION</p>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td class="receipt-header-copy" valign="middle" style="padding-right:20px;"><h1 style="margin:0;font-family:Georgia,'Times New Roman',serif;font-size:28px;font-weight:normal;line-height:1.25;color:#f4eedc;">Thank you for supporting the village.</h1><p style="margin:14px 0 0;font-size:13px;line-height:1.8;color:#d4ddce;">A little more you. A little more light in the village.</p></td><td class="receipt-art" width="150" valign="middle"><img src="{{ $receipt['art_url'] }}" width="150" alt="" style="display:block;width:150px;max-width:100%;height:auto;border:1px solid #ad9e78;"></td></tr></table>
            </td></tr>
            <tr><td style="padding:28px;">
                <p style="margin:0 0 8px;font-size:11px;letter-spacing:1.5px;color:#776444;">YOUR PURCHASE</p>
                <h2 style="margin:0 0 20px;font-family:Georgia,'Times New Roman',serif;font-size:27px;font-weight:normal;line-height:1.3;">{{ $receipt['name'] }}</h2>
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top:1px solid #d0cebd;border-bottom:1px solid #d0cebd;font-size:13px;line-height:1.8;">
                    <tbody><tr><th scope="row" align="left" style="padding:12px 8px 8px 0;font-weight:normal;color:#59665d;">Amount paid</th><td align="right" style="padding:12px 0 8px;font-weight:bold;">{{ $receipt['amount_label'] }}</td></tr><tr><th scope="row" align="left" style="padding:4px 8px 8px 0;font-weight:normal;color:#59665d;">Payment confirmed</th><td align="right" style="padding:4px 0 8px;">{{ $receipt['paid_at'] }}</td></tr><tr><th scope="row" align="left" style="padding:4px 8px 12px 0;font-weight:normal;color:#59665d;">Order reference</th><td align="right" style="padding:4px 0 12px;font-size:11px;word-break:break-all;">{{ $receipt['order_id'] }}</td></tr></tbody>
                </table>
                <h3 style="margin:24px 0 12px;font-family:Georgia,'Times New Roman',serif;font-size:20px;font-weight:normal;">Included in your pack</h3>
                <ul style="margin:0 0 24px;padding-left:20px;font-size:13px;line-height:1.9;">
                    @foreach ($receipt['items'] as $item)
                        <li style="padding-bottom:4px;">{{ $item }}</li>
                    @endforeach
                </ul>
                <p style="margin:0 0 20px;font-size:13px;line-height:1.8;">Keep this email as your purchase confirmation. Your purchase history shows your pack’s contents, match use and refund options.</p>
                <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td style="background-color:#456045;border:1px solid #456045;"><a href="{{ $receipt['purchases_url'] }}" style="display:inline-block;padding:12px 18px;font-size:13px;font-weight:bold;line-height:1.5;text-decoration:none;color:#fffdf3;">View your purchases &rarr;</a></td></tr></table>
                <div style="margin-top:28px;border-top:1px solid #d0cebd;padding-top:23px;">
                    <h3 style="margin:0 0 12px;font-family:Georgia,'Times New Roman',serif;font-size:21px;font-weight:normal;">Your refund information</h3>
                    <p style="margin:0 0 17px;font-size:12px;line-height:1.9;">{!! nl2br(e($receipt['policy_text'])) !!}</p>
                    <p style="margin:0 0 18px;font-size:12px;line-height:1.9;">You can always contact us about faulty or undelivered content. Repeated refunded purchases may require support review before buying again.</p>
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td style="border:1px solid #8e9b85;"><a href="{{ $receipt['refunds_url'] }}" style="display:inline-block;padding:10px 16px;font-size:12px;line-height:1.5;text-decoration:none;color:#36513c;">Refunds &amp; purchase support &rarr;</a></td></tr></table>
                </div>
                <div style="margin-top:26px;padding:18px;background-color:#e9e3d3;border-left:3px solid #9b8250;">
                    <h3 style="margin:0 0 11px;font-family:Georgia,'Times New Roman',serif;font-size:19px;font-weight:normal;">Your digital content choice</h3>
                    @if ($receipt['consent_text'])
                        <p style="margin:0;font-size:12px;line-height:1.9;">{!! nl2br(e($receipt['consent_text'])) !!}</p>
                        @if ($receipt['consent_accepted_at'])
                            <p style="margin:12px 0 0;font-size:11px;line-height:1.8;color:#59665d;">Accepted at checkout: {{ $receipt['consent_accepted_at'] }}</p>
                        @endif
                    @else
                        <p style="margin:0;font-size:12px;line-height:1.9;">No immediate-supply consent was recorded for this purchase. Your existing statutory rights are preserved. This confirmation does not retrospectively waive them.</p>
                    @endif
                </div>
                <p style="margin:26px 0 0;font-size:13px;line-height:1.9;">A question about your purchase? Email <a href="mailto:{{ $receipt['support_email'] }}" style="color:#36513c;text-decoration:underline;word-break:break-word;">{{ $receipt['support_email'] }}</a> and include your order reference.</p>
            </td></tr>
            <tr><td style="padding:22px 28px;border-top:1px solid #d0cebd;background-color:#ede7d8;font-size:11px;line-height:1.9;color:#59665d;">
                @if ($receipt['operator_name'])<p style="margin:0 0 5px;font-weight:bold;color:#344b3e;">{{ $receipt['operator_name'] }}</p>@endif
                @if ($receipt['business_address'])<p style="margin:0 0 5px;">{!! nl2br(e($receipt['business_address'])) !!}</p>@endif
                @if ($receipt['registration_number'])<p style="margin:0 0 10px;">Registration number: {{ $receipt['registration_number'] }}</p>@endif
                <p style="margin:0;"><a href="{{ $receipt['terms_url'] }}" style="color:#36513c;">Terms of Service</a> &nbsp;·&nbsp; <a href="{{ $receipt['privacy_url'] }}" style="color:#36513c;">Privacy Notice</a> &nbsp;·&nbsp; <a href="{{ $receipt['refunds_url'] }}" style="color:#36513c;">Refunds</a></p>
                <p style="margin:12px 0 0;font-family:Georgia,'Times New Roman',serif;font-style:italic;">Good company. A village worth coming back to.</p>
            </td></tr>
        </table>
    </td></tr></table>
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark light">
    <title>@yield('title')</title>
</head>
<body style="margin:0;padding:0;width:100%;background-color:#101d20;color:#eee9d5;font-family:Arial,Helvetica,sans-serif;">
    <div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">@yield('preheader')</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#101d20" style="width:100%;background-color:#101d20;">
        <tr>
            <td align="center" style="padding:32px 12px;">
                <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;">
                    <tr>
                        <td style="padding:0 20px 24px;">
                            <a href="{{ route('home') }}" style="font-family:Georgia,'Times New Roman',serif;font-size:27px;line-height:34px;letter-spacing:-1px;color:#eee9d5;text-decoration:none;">who’s chanting<span style="color:#e19b82;">?</span></a>
                        </td>
                    </tr>
                    <tr>
                        <td bgcolor="#18282b" style="background-color:#18282b;">
                            <img src="{{ $message->embed(public_path('assets/chanting/welcome-email-storybook.png')) }}" width="600" alt="Illustrated villagers welcome you to a lantern-lit table in Who’s Chanting?" style="display:block;width:100%;max-width:600px;height:auto;border:0;color:#eee9d5;font-size:14px;">
                        </td>
                    </tr>
                    <tr>
                        <td bgcolor="#18282b" style="padding:32px;background-color:#18282b;">
                            <p style="margin:0 0 14px;font-size:11px;line-height:18px;font-weight:bold;letter-spacing:2px;color:#bdcd9c;">@yield('eyebrow')</p>
                            <h1 style="margin:0 0 22px;font-family:Georgia,'Times New Roman',serif;font-size:34px;line-height:40px;font-weight:normal;letter-spacing:-1px;color:#eee9d5;">@yield('heading')</h1>
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 20px 0;">
                            <p style="margin:0 0 8px;font-family:Georgia,'Times New Roman',serif;font-size:18px;line-height:26px;color:#bdcd9c;">Trust your friends. Mostly.</p>
                            <p style="margin:0;font-size:12px;line-height:20px;color:#aebdb6;">A suspicious little village. A very good night with friends.</p>
                        </td>
                    </tr>
                </table>
                <!--[if mso]></td></tr></table><![endif]-->
            </td>
        </tr>
    </table>
</body>
</html>

@extends('emails.layout')

@section('title', 'Verify your email · Who’s Chanting?')
@section('preheader', 'One quick check before you settle into the village. Verify your email to finish creating your account.')
@section('eyebrow', 'YOUR INVITATION TO THE VILLAGE')
@section('heading', 'One last thing before nightfall.')

@section('content')
    <p style="margin:0 0 16px;font-size:16px;line-height:26px;color:#eee9d5;">Hello {{ $name }},</p>
    <p style="margin:0 0 24px;font-size:16px;line-height:26px;color:#d4ddd2;">Your place in Who’s Chanting? is almost ready. Confirm this is your email address to finish creating your account.</p>
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
        <tr>
            <td bgcolor="#bdcd9c" style="background-color:#bdcd9c;border-radius:4px;text-align:center;">
                <a href="{{ $verificationUrl }}" style="display:inline-block;padding:16px 24px;border:1px solid #bdcd9c;border-radius:4px;font-size:15px;line-height:20px;font-weight:bold;text-decoration:none;color:#101d20;mso-padding-alt:0;">
                    <!--[if mso]><i style="mso-font-width:120%;mso-text-raise:24pt;" hidden>&emsp;</i><![endif]-->
                    <span style="mso-text-raise:12pt;">Verify my email</span>
                    <!--[if mso]><i style="mso-font-width:120%;" hidden>&emsp;&#8203;</i><![endif]-->
                </a>
            </td>
        </tr>
    </table>
    <p style="margin:0 0 24px;font-size:14px;line-height:23px;color:#aebdb6;">This link expires in {{ $expiresIn }} minutes. If you didn’t create an account, you can ignore this email.</p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-top:1px solid #344346;">
        <tr>
            <td style="padding-top:20px;">
                <p style="margin:0 0 8px;font-size:12px;line-height:20px;color:#aebdb6;">Button not working? Copy this link into your browser:</p>
                <p style="margin:0;font-size:12px;line-height:20px;word-break:break-all;overflow-wrap:anywhere;"><a href="{{ $verificationUrl }}" style="color:#bdcd9c;text-decoration:underline;word-break:break-all;overflow-wrap:anywhere;">{{ $verificationUrl }}</a></p>
            </td>
        </tr>
    </table>
@endsection

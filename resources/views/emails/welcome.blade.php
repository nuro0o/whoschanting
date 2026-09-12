@extends('emails.layout', [
    'heroImage' => 'assets/chanting/welcome-email-ferryman.jpg',
    'heroAlt' => 'From your seat aboard the boat, the hooded Ferryman rows you toward the lantern-lit village.',
])

@section('title', 'Welcome to Who’s Chanting?')
@section('preheader', 'Your email is verified. Pick a character, gather your friends, and meet us in the village.')
@section('eyebrow', 'YOU’RE ONE OF THE LOCALS NOW')
@section('heading', 'Welcome to Who’s Chanting?')

@section('content')
    <p style="margin:0 0 16px;font-size:16px;line-height:26px;color:#eee9d5;">Hello {{ $name }},</p>
    <p style="margin:0 0 24px;font-size:16px;line-height:26px;color:#d4ddd2;">Your email is verified, and the village has saved you a seat. It’s a lovely place. Try not to trust everyone in it.</p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-top:1px solid #344346;border-bottom:1px solid #344346;">
        <tr>
            <td width="34" valign="top" style="padding:22px 10px 0 0;font-family:Georgia,'Times New Roman',serif;font-size:24px;line-height:28px;color:#bdcd9c;">01</td>
            <td style="padding:22px 0 0;">
                <p style="margin:0 0 5px;font-size:15px;line-height:23px;font-weight:bold;color:#eee9d5;">Choose your village face.</p>
                <p style="margin:0;font-size:14px;line-height:23px;color:#d4ddd2;">Pick any character when you join a room. Your look is purely cosmetic and says nothing about your secret role.</p>
            </td>
        </tr>
        <tr>
            <td width="34" valign="top" style="padding:20px 10px 0 0;font-family:Georgia,'Times New Roman',serif;font-size:24px;line-height:28px;color:#bdcd9c;">02</td>
            <td style="padding:20px 0 0;">
                <p style="margin:0 0 5px;font-size:15px;line-height:23px;font-weight:bold;color:#eee9d5;">Gather the usual suspects.</p>
                <p style="margin:0;font-size:14px;line-height:23px;color:#d4ddd2;">Create a room and share its code with your friends. You’ll need {{ config('game.min_players') }}–{{ config('game.max_players') }} players in total.</p>
            </td>
        </tr>
        <tr>
            <td width="34" valign="top" style="padding:20px 10px 22px 0;font-family:Georgia,'Times New Roman',serif;font-size:24px;line-height:28px;color:#bdcd9c;">03</td>
            <td style="padding:20px 0 22px;">
                <p style="margin:0 0 5px;font-size:15px;line-height:23px;font-weight:bold;color:#eee9d5;">Let the suspicion begin.</p>
                <p style="margin:0;font-size:14px;line-height:23px;color:#d4ddd2;">Act at night, discuss what happened, then vote. Keep your private role secret and read the room.</p>
            </td>
        </tr>
    </table>
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:26px 0 18px;">
        <tr>
            <td bgcolor="#bdcd9c" style="background-color:#bdcd9c;border-radius:4px;text-align:center;">
                <a href="{{ route('home') }}" style="display:inline-block;padding:16px 24px;border:1px solid #bdcd9c;border-radius:4px;font-size:15px;line-height:20px;font-weight:bold;text-decoration:none;color:#101d20;mso-padding-alt:0;">
                    <!--[if mso]><i style="mso-font-width:120%;mso-text-raise:24pt;" hidden>&emsp;</i><![endif]-->
                    <span style="mso-text-raise:12pt;">Enter the village</span>
                    <!--[if mso]><i style="mso-font-width:120%;" hidden>&emsp;&#8203;</i><![endif]-->
                </a>
            </td>
        </tr>
    </table>
    <p style="margin:0;font-size:14px;line-height:23px;color:#aebdb6;">A little preparation never hurt. <a href="{{ route('home') }}#how-to-play" style="color:#bdcd9c;text-decoration:underline;">Read how to play</a>.</p>
@endsection

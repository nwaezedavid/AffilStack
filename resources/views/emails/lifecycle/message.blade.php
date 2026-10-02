{{-- Lifecycle emails (birthday, festive season, re-engagement): one
     table-based, inline-styled layout so it renders the same in Gmail,
     Outlook, Apple Mail and on phones. Content comes pre-rendered from
     LifecycleEmailRenderer — $bodyHtml is Markdown it already converted
     with raw HTML escaped, so it's safe to output unescaped here. --}}
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $subject }}</title>
    <style>
        @media only screen and (max-width: 620px) {
            .container { width: 100% !important; }
            .px { padding-left: 22px !important; padding-right: 22px !important; }
            .heading { font-size: 26px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#F1F4F9;-webkit-text-size-adjust:100%;">
    @if ($preheader !== '')
        <div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:#F1F4F9;opacity:0;">
            {{ $preheader }}&#8202;&zwnj;&nbsp;&#8202;&zwnj;&nbsp;&#8202;&zwnj;&nbsp;&#8202;&zwnj;&nbsp;
        </div>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F1F4F9;">
        <tr>
            <td align="center" style="padding:28px 12px;">
                <table role="presentation" class="container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;">
                    {{-- Brand bar --}}
                    <tr>
                        <td align="left" style="padding:0 4px 14px;font-family:Arial,Helvetica,sans-serif;">
                            @if ($logoUrl)
                                <img src="{{ $logoUrl }}" alt="{{ $siteName }}" height="32" style="display:block;height:32px;width:auto;border:0;">
                            @else
                                <span style="font-size:20px;font-weight:700;color:#0B1E3D;letter-spacing:-0.3px;">{{ $siteName }}</span>
                            @endif
                        </td>
                    </tr>

                    {{-- Card --}}
                    <tr>
                        <td style="background-color:#FFFFFF;border-radius:18px;overflow:hidden;box-shadow:0 1px 3px rgba(11,30,61,0.08);">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                {{-- Accent band + heading --}}
                                <tr>
                                    <td class="px" style="background-color:{{ $themeColor }};background-image:linear-gradient(135deg, {{ $themeColor }} 0%, #0B1E3D 100%);padding:36px 40px 32px;border-radius:18px 18px 0 0;font-family:Arial,Helvetica,sans-serif;">
                                        <div style="width:44px;height:4px;background-color:#E5B93C;border-radius:4px;margin-bottom:18px;font-size:0;line-height:0;">&nbsp;</div>
                                        <h1 class="heading" style="margin:0;font-size:30px;line-height:1.2;font-weight:700;color:#FFFFFF;letter-spacing:-0.4px;">{{ $heading }}</h1>
                                        @if ($preheader !== '')
                                            <p style="margin:10px 0 0;font-size:15px;line-height:1.5;color:rgba(255,255,255,0.85);">{{ $preheader }}</p>
                                        @endif
                                    </td>
                                </tr>

                                @if ($heroUrl)
                                    <tr>
                                        <td style="padding:0;">
                                            <img src="{{ $heroUrl }}" alt="" width="600" style="display:block;width:100%;max-width:600px;height:auto;border:0;">
                                        </td>
                                    </tr>
                                @endif

                                {{-- Body --}}
                                <tr>
                                    <td class="px" style="padding:32px 40px 8px;font-family:Arial,Helvetica,sans-serif;">
                                        {!! $bodyHtml !!}
                                    </td>
                                </tr>

                                @if ($ctaLabel && $ctaUrl)
                                    <tr>
                                        <td class="px" style="padding:8px 40px 36px;font-family:Arial,Helvetica,sans-serif;">
                                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                                <tr>
                                                    <td align="center" bgcolor="#0B1E3D" style="border-radius:999px;background-color:#0B1E3D;">
                                                        <a href="{{ $ctaUrl }}" target="_blank" style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:700;color:#FFFFFF;text-decoration:none;border-radius:999px;">{{ $ctaLabel }} &rarr;</a>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                @else
                                    <tr><td style="padding:0 0 20px;font-size:0;line-height:0;">&nbsp;</td></tr>
                                @endif
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td align="center" class="px" style="padding:22px 28px 8px;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.6;color:#64748B;">
                            You're receiving this because you have an account with {{ $siteName }} and birthday, holiday and tips emails are switched on.
                            @if ($unsubscribeUrl)
                                <br>
                                <a href="{{ $profileUrl }}?tab=notifications" style="color:#64748B;text-decoration:underline;">Email preferences</a>
                                &nbsp;&middot;&nbsp;
                                <a href="{{ $unsubscribeUrl }}" style="color:#64748B;text-decoration:underline;">Unsubscribe in one click</a>
                            @endif
                            <br>&copy; {{ now()->year }} {{ $siteName }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

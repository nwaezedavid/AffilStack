{{-- CRM/email dashboard: plain HTML (not the Markdown mail theme) so the
     AI-generated $body — which may contain underscores/asterisks that
     would otherwise be misread as Markdown emphasis — renders exactly as
     drafted, with only real HTML (line breaks) added on top. --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #18181b; font-size: 15px; line-height: 1.6; max-width: 560px; margin: 0 auto; padding: 24px 16px;">
    <div>{!! nl2br(e($body)) !!}</div>

    <p style="margin-top: 24px;">
        Thanks,<br>
        {{ $senderName }}
    </p>

    <hr style="border: none; border-top: 1px solid #e4e4e7; margin: 32px 0 16px;">

    <p style="font-size: 12px; color: #71717a;">
        You're receiving this because {{ $senderName }} added you as a contact on AffilStack.
        <a href="{{ $unsubscribeUrl }}" style="color: #71717a;">Unsubscribe</a> to stop future emails.
    </p>

    <img src="{{ $openPixelUrl }}" width="1" height="1" alt="" style="display:none; width:1px; height:1px;">
</body>
</html>

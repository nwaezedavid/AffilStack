{{-- A CRM campaign email / inbox reply: plain, personal-looking HTML (a
     cold email that looks like a newsletter lands in Promotions), with the
     compliance footer — business name, postal address, one-click
     unsubscribe — that CAN-SPAM / GDPR-style rules require. --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #18181b; font-size: 15px; line-height: 1.6; max-width: 600px; margin: 0; padding: 16px;">
    <div>{!! $bodyHtml !!}</div>

    @if ($senderName)
        <p style="margin-top: 4px;">{{ $senderName }}</p>
    @endif

    @if ($quotedHtml)
        <div style="margin-top: 24px; padding-left: 12px; border-left: 2px solid #e4e4e7; color: #52525b; font-size: 13px;">{!! $quotedHtml !!}</div>
    @endif

    @if ($unsubscribeUrl)
        <p style="margin-top: 32px; font-size: 11px; line-height: 1.5; color: #a1a1aa;">
            @if ($businessName){{ $businessName }}@endif @if ($businessAddress) · {{ $businessAddress }}@endif<br>
            Not interested? <a href="{{ $unsubscribeUrl }}" style="color: #a1a1aa;">Unsubscribe</a> and you won't hear from us again.
        </p>
    @endif

    @if ($openPixelUrl)
        <img src="{{ $openPixelUrl }}" width="1" height="1" alt="" style="display:none; width:1px; height:1px;">
    @endif
</body>
</html>

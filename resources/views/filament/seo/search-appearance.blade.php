{{--
    Live "how this page looks out there" preview for the SEO editors: a Google
    result (favicon + site name + URL + title + description) and social cards
    for Facebook, X and LinkedIn. Inline styles on purpose — Filament's
    compiled CSS doesn't include arbitrary Tailwind utilities, and the mocks
    should look like the real thing in light and dark admin themes alike.
--}}
<?php
    $displayUrl = preg_replace('#^https?://#', '', rtrim($url, '/'));
    $crumbs = array_values(array_filter(explode('/', (string) parse_url($url, PHP_URL_PATH))));
    $truncate = fn (string $text, int $max) => mb_strlen($text) > $max ? rtrim(mb_substr($text, 0, $max - 1)).'…' : $text;
    $font = "font-family: Arial, 'Helvetica Neue', Helvetica, sans-serif;";
?>
<div style="display: grid; grid-template-columns: minmax(0, 1fr); gap: 14px; width: 100%; max-width: 100%; min-width: 0; overflow: hidden; overflow-wrap: anywhere;">
    {{-- Google --}}
    <div>
        <div style="font-size: 11px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; opacity: .6; margin-bottom: 6px;">Google search result</div>
        <div style="background: #fff; color: #202124; border: 1px solid #dadce0; border-radius: 12px; padding: 14px 16px; {{ $font }}">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="display: inline-flex; width: 28px; height: 28px; border-radius: 50%; background: #f1f3f4; align-items: center; justify-content: center; border: 1px solid #ecedef; flex-shrink: 0;">
                    <img src="{{ $favicon }}" alt="" width="18" height="18" style="width: 18px; height: 18px; border-radius: 3px;">
                </span>
                <span style="line-height: 1.25; min-width: 0;">
                    <span style="display: block; font-size: 14px; color: #202124;">{{ $site_name }}</span>
                    <span style="display: block; font-size: 12px; color: #4d5156; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ 'https://'.$host }}@foreach ($crumbs as $crumb) › {{ $crumb }}@endforeach</span>
                </span>
            </div>
            <div style="margin-top: 8px; font-size: 20px; line-height: 1.3; color: #1a0dab;">{{ $truncate($title, 62) }}</div>
            <div style="margin-top: 4px; font-size: 14px; line-height: 1.58; color: #4d5156;">
                @if ($description)
                    {{ $truncate($description, 160) }}
                @else
                    <em style="color: #b3261e;">No description — Google will pick some text from the page.</em>
                @endif
            </div>
            @if ($robots && str_contains($robots, 'noindex'))
                <div style="margin-top: 8px; font-size: 12px; color: #b3261e; font-weight: 600;">noindex is on — this result will not appear in Google.</div>
            @endif
        </div>
    </div>

    {{-- Facebook --}}
    <div>
        <div style="font-size: 11px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; opacity: .6; margin-bottom: 6px;">Facebook / WhatsApp</div>
        <div style="background: #fff; color: #050505; border: 1px solid #dadde1; border-radius: 8px; overflow: hidden; {{ $font }}">
            @if ($og['image'])
                <div style="aspect-ratio: 1.91 / 1; background: #f0f2f5 url('{{ $og['image']['url'] }}') center / cover no-repeat;"></div>
            @else
                <div style="aspect-ratio: 1.91 / 1; background: #f0f2f5; display: flex; align-items: center; justify-content: center; color: #65676b; font-size: 12px; text-align: center; padding: 0 16px;">No social image — upload a 1200×630 image or set a default in SEO settings</div>
            @endif
            <div style="background: #f0f2f5; padding: 10px 12px; border-top: 1px solid #dadde1;">
                <div style="font-size: 12px; color: #65676b; text-transform: uppercase;">{{ $host }}</div>
                <div style="font-size: 16px; font-weight: 600; line-height: 1.25; margin-top: 2px;">{{ $truncate($og['title'], 88) }}</div>
                <div style="font-size: 14px; color: #65676b; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $og['description'] }}</div>
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 180px), 1fr)); gap: 14px; min-width: 0;">
        {{-- X --}}
        <div>
            <div style="font-size: 11px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; opacity: .6; margin-bottom: 6px;">X (Twitter)</div>
            <div style="position: relative; border-radius: 16px; overflow: hidden; border: 1px solid #cfd9de; background: #fff; {{ $font }}">
                @if ($twitter['image'])
                    <div style="aspect-ratio: 1.91 / 1; background: #eff3f4 url('{{ $twitter['image']['url'] }}') center / cover no-repeat;"></div>
                    <span style="position: absolute; left: 10px; bottom: 10px; background: rgba(0,0,0,.77); color: #fff; font-size: 12px; padding: 2px 6px; border-radius: 4px; max-width: 85%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $truncate($twitter['title'], 70) }}</span>
                @else
                    <div style="padding: 12px; color: #0f1419;">
                        <div style="font-size: 13px; color: #536471;">{{ $host }}</div>
                        <div style="font-size: 15px;">{{ $truncate($twitter['title'], 70) }}</div>
                        <div style="font-size: 13px; color: #536471;">{{ $truncate((string) $twitter['description'], 100) }}</div>
                    </div>
                @endif
            </div>
            <div style="font-size: 12px; opacity: .6; margin-top: 4px;">From {{ $host }}</div>
        </div>

        {{-- LinkedIn --}}
        <div>
            <div style="font-size: 11px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; opacity: .6; margin-bottom: 6px;">LinkedIn</div>
            <div style="border: 1px solid #e0dfdc; border-radius: 8px; overflow: hidden; background: #fff; color: rgba(0,0,0,.9); {{ $font }}">
                @if ($og['image'])
                    <div style="aspect-ratio: 1.91 / 1; background: #f3f2ef url('{{ $og['image']['url'] }}') center / cover no-repeat;"></div>
                @endif
                <div style="padding: 8px 12px; background: #eef3f8;">
                    <div style="font-size: 14px; font-weight: 600; line-height: 1.3;">{{ $truncate($og['title'], 70) }}</div>
                    <div style="font-size: 12px; color: rgba(0,0,0,.6); margin-top: 2px;">{{ $host }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

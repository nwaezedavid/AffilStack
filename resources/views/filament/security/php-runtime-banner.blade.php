@php
    $problem = collect(\App\Support\Security\PhpRuntime::problemsFor($web['version'], false))->firstWhere('severity', 'critical');
@endphp
@if ($problem)
    <div role="alert" style="margin-bottom:1rem;border-radius:.75rem;border:1px solid #fecaca;background:#fef2f2;padding:.875rem 1rem;color:#7f1d1d;font-size:.875rem;line-height:1.5">
        <strong style="display:block;font-size:.9375rem">{{ $problem['title'] }}</strong>
        <span>{{ $problem['detail'] }}</span>
        <a href="{{ route('filament.admin.resources.security-findings.index') }}" style="margin-left:.25rem;font-weight:600;text-decoration:underline;color:#991b1b">Security Center</a>
    </div>
@endif

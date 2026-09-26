@extends('layouts.marketing')

@section('title', $page->displayTitle().' — '.\App\Models\SiteSetting::get('site_name', 'AffilStack'))
@section('meta_description', $page->meta_description ?: '')
@if ($page->og_image_path)
    @section('og_image', \Illuminate\Support\Facades\Storage::disk('public')->url($page->og_image_path))
@endif
@if ($page->no_index)
    @section('robots', 'noindex, follow')
@endif

@section('content')
    <article class="max-w-3xl mx-auto px-6 py-16">
        <x-breadcrumbs :trail="[
            ['label' => 'Home', 'url' => route('home')],
            ['label' => $page->title, 'url' => null],
        ]" />

        <h1 class="font-display font-semibold text-3xl sm:text-4xl text-navy-900 text-wrap-balance">{{ $page->title }}</h1>
        <p class="text-xs text-ink-400 mt-2">Last updated {{ $page->updated_at->format('F j, Y') }}</p>

        <div class="mt-8 text-sm sm:text-base text-ink-600 leading-relaxed
            [&_h2]:font-display [&_h2]:font-semibold [&_h2]:text-navy-900 [&_h2]:text-xl [&_h2]:mt-8 [&_h2]:mb-2
            [&_h3]:font-display [&_h3]:font-semibold [&_h3]:text-navy-900 [&_h3]:mt-6 [&_h3]:mb-2
            [&_p]:mb-4 [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:mb-4 [&_ul]:space-y-1 [&_ol]:list-decimal [&_ol]:pl-5 [&_ol]:mb-4 [&_ol]:space-y-1
            [&_a]:text-brand-600 [&_a]:underline [&_strong]:text-navy-900">
            {!! \App\Support\SafeHtml::clean($page->content) !!}
        </div>
    </article>
@endsection

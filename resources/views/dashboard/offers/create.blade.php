@extends('layouts.app')

@section('title', 'Research a new product')

@section('content')
    <x-ui.page-header title="Research a new product" eyebrow="Step 1" icon="magnifying-glass-circle"
                      subtitle="Give the AI the product's name, link and affiliate platform — it works out who buys it, where to find them and how to sell it." />
    <div class="max-w-3xl">
        @include('dashboard.offers.partials.research-form')
    </div>
@endsection

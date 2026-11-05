{{-- One-click video: pick a style, press Create. Everything else (script,
     presenter, voice, product photo, captions) is decided by AffilStack;
     "Customise" lets anyone who wants to change it. See QuickVideoService. --}}
@php
    $quickStyles = $quickStyles ?? [];
    $anyQuickStyle = collect($quickStyles)->contains('available', true);
    $quickPending = $ugcGens->first(fn ($g) => $g->module === 'ugc_video' && $g->status === 'queued' && ! empty($g->input['quick_style']));
    $defaultStyle = old('style', collect($quickStyles)->filter(fn ($s) => $s['available'])->keys()->first());
    $canCreate = ! auth()->user()->isSeat();
    $styleTones = [
        'talking_review' => ['ring' => 'has-[:checked]:ring-rose-500', 'icon' => 'bg-rose-100 text-rose-600'],
        'product_showcase' => ['ring' => 'has-[:checked]:ring-violet-500', 'icon' => 'bg-violet-100 text-violet-600'],
        'problem_solution' => ['ring' => 'has-[:checked]:ring-amber-500', 'icon' => 'bg-amber-100 text-amber-600'],
    ];
@endphp

<section class="mb-6 overflow-hidden rounded-3xl border border-line bg-white shadow-[0_10px_40px_-18px_rgba(11,30,61,0.25)]" aria-labelledby="quick-video-heading" data-quick-video>
    <div class="bg-gradient-to-r from-navy-950 via-navy-900 to-rose-900 px-5 py-5 text-white sm:px-6">
        <p class="text-[11px] font-mono uppercase tracking-[0.18em] text-rose-200">Create a video in two clicks</p>
        <h3 id="quick-video-heading" class="mt-1 font-display text-xl font-semibold">Pick a style, press Create</h3>
        <p class="mt-1 max-w-2xl text-sm text-white/75">AffilStack writes the script from your research on {{ $offer->product_name }}, picks the presenter, voice and visuals, and gives you a vertical video with captions, ready to publish. No typing needed.</p>
    </div>

    @if ($quickPending)
        <div class="flex items-center gap-3 border-b border-line bg-rose-50 px-5 py-4 text-sm text-rose-800 sm:px-6" role="status">
            <x-heroicon-m-sparkles class="h-5 w-5 shrink-0 animate-pulse" />
            <span>Your {{ \App\Services\Video\VideoStyles::STYLES[$quickPending->input['quick_style']]['label'] ?? '' }} video is being created. It usually takes 2 to 10 minutes — this page updates by itself, and we'll notify you.</span>
        </div>
    @endif

    @if (! $anyQuickStyle)
        <div class="flex items-start gap-3 p-5 text-sm text-amber-900 sm:p-6">
            <x-heroicon-o-wrench-screwdriver class="h-5 w-5 shrink-0 text-amber-600" />
            <p><strong>Video creation is being set up.</strong> Our team is connecting the video engines. You can still write and edit scripts in the step-by-step studio below.</p>
        </div>
    @else
        <form method="POST" action="{{ route('offers.ugc.quick', $offer) }}" enctype="multipart/form-data" class="p-5 sm:p-6" data-quick-form>
            @csrf
            <fieldset>
                <legend class="mb-3 text-sm font-semibold text-navy-900">1. Choose a style</legend>
                <div class="grid gap-3 md:grid-cols-3">
                    @foreach ($quickStyles as $key => $style)
                        <label @class([
                            'relative flex cursor-pointer flex-col gap-2 rounded-2xl bg-white p-4 ring-1 ring-line transition hover:ring-ink-400 has-[:checked]:ring-2',
                            $styleTones[$key]['ring'] ?? 'has-[:checked]:ring-brand-500',
                            'pointer-events-none opacity-50' => ! $style['available'],
                        ])>
                            <input type="radio" name="style" value="{{ $key }}" class="peer sr-only" data-style-input data-uses-image="{{ $style['provider'] === 'higgsfield' ? '1' : '0' }}" data-uses-presenter="{{ $style['provider'] === 'heygen' ? '1' : '0' }}" @checked($defaultStyle === $key) @disabled(! $style['available'])>
                            <span class="flex items-center gap-2.5">
                                <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl {{ $styleTones[$key]['icon'] ?? 'bg-brand-50 text-brand-600' }}">
                                    <x-dynamic-component :component="'heroicon-o-'.$style['icon']" class="h-5 w-5" />
                                </span>
                                <span class="font-semibold text-navy-900">{{ $style['label'] }}</span>
                            </span>
                            <span class="text-sm text-ink-600">{{ $style['description'] }}</span>
                            <span class="mt-auto flex flex-wrap gap-x-3 gap-y-1 pt-1 text-xs text-ink-400">
                                <span class="inline-flex items-center gap-1 font-semibold text-ink-700"><x-heroicon-m-bolt class="h-3.5 w-3.5 text-gold-500" /> {{ $style['credits'] }} credits</span>
                                <span>~{{ $style['duration'] }}s · 9:16</span>
                                @unless ($style['available'])
                                    <span>Coming soon</span>
                                @endunless
                            </span>
                            <span class="absolute right-3 top-3 hidden h-6 w-6 items-center justify-center rounded-full bg-navy-900 text-white peer-checked:inline-flex"><x-heroicon-m-check class="h-4 w-4" /></span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <details class="group mt-4 rounded-2xl bg-surface-muted p-4" @if (old('script') || $errors->has('script') || $errors->has('product_image')) open @endif>
                <summary class="cursor-pointer list-none text-sm font-medium text-brand-700 [&::-webkit-details-marker]:hidden">
                    <x-heroicon-m-adjustments-horizontal class="inline h-4 w-4" /> Customise (optional) — your own script, presenter or product photo
                </summary>
                <div class="mt-4 grid gap-4">
                    <label class="block">
                        <span class="flex flex-wrap items-center justify-between gap-2 text-sm font-medium text-navy-900">
                            <span>Your own script <span class="font-normal text-ink-400">(leave empty and AffilStack writes it)</span></span>
                            <span class="text-xs font-normal text-ink-400" data-quick-meter></span>
                        </span>
                        <textarea name="script" rows="5" maxlength="{{ \App\Http\Controllers\Dashboard\UgcController::MAX_VIDEO_SCRIPT_CHARACTERS }}" data-quick-script
                                  placeholder="Exactly what should be said in the video. Plain sentences, no headings."
                                  class="mt-1.5 w-full rounded-xl border border-line bg-white px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-rose-500">{{ old('script') }}</textarea>
                        <span class="mt-1 block text-xs text-ink-400">Short styles (product showcase, problem → solution) only have time for one or two sentences.</span>
                        @error('script')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
                    </label>

                    @if ($ugcAvatars !== [] && $ugcVoices !== [])
                        <div class="grid gap-3 sm:grid-cols-2" data-presenter-fields>
                            <label class="block">
                                <span class="mb-1 block text-xs font-medium text-ink-600">Presenter (Talking review)</span>
                                <select name="avatar_id" class="w-full rounded-xl border border-line bg-white px-3 py-2 text-sm">
                                    <option value="">Pick one for me</option>
                                    @foreach ($ugcAvatars as $avatar)
                                        <option value="{{ $avatar['id'] }}" @selected(old('avatar_id') === $avatar['id'])>{{ $avatar['name'] }}{{ $avatar['gender'] ? ' · '.ucfirst($avatar['gender']) : '' }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-xs font-medium text-ink-600">Voice (Talking review)</span>
                                <select name="voice_id" class="w-full rounded-xl border border-line bg-white px-3 py-2 text-sm">
                                    <option value="">Match the presenter</option>
                                    @foreach ($ugcVoices as $voice)
                                        <option value="{{ $voice['id'] }}" @selected(old('voice_id') === $voice['id'])>{{ $voice['name'] }}{{ $voice['language'] ? ' · '.$voice['language'] : '' }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    @endif

                    <div data-image-fields>
                        <span class="mb-1 block text-xs font-medium text-ink-600">Product photo (Product showcase and Problem → solution)</span>
                        <div class="flex flex-wrap items-center gap-3">
                            @if ($productImageUrl)
                                <img src="{{ $productImageUrl }}" alt="Current product photo" class="h-16 w-16 rounded-xl object-cover ring-1 ring-line">
                                <span class="text-xs text-ink-600">Using this photo. Upload another to replace it.</span>
                            @else
                                <span class="text-xs text-ink-600">AffilStack takes the picture from the product's website. Upload one if you'd like a different photo.</span>
                            @endif
                        </div>
                        <input type="file" name="product_image" accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full text-sm text-ink-600 file:mr-3 file:rounded-full file:border-0 file:bg-white file:px-4 file:py-2 file:text-sm file:font-medium file:text-navy-900 file:ring-1 file:ring-line">
                        <span class="mt-1 block text-xs text-ink-400">JPG, PNG or WebP, at least 300×300, up to 8 MB.</span>
                        @error('product_image')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
                    </div>
                </div>
            </details>

            <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-ink-600">2. Press Create. You're only charged when your video is ready.</p>
                @if (! $canCreate)
                    <p class="text-sm text-ink-600">Ask your account owner to create videos.</p>
                @else
                    <button type="submit" @disabled($quickPending) class="inline-flex items-center justify-center gap-2 rounded-full bg-gradient-to-r from-rose-500 to-pink-600 px-7 py-3 text-sm font-semibold text-white shadow-lg shadow-rose-500/25 transition hover:from-rose-600 hover:to-pink-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-rose-500 disabled:opacity-60 whitespace-nowrap" data-quick-submit>
                        <x-heroicon-m-video-camera class="h-5 w-5" /> {{ $quickPending ? 'Creating your video…' : 'Create video' }}
                    </button>
                @endif
            </div>
        </form>
    @endif
</section>

@push('scripts')
    <script>
        (function () {
            const form = document.querySelector('[data-quick-form]');
            if (!form) {
                return;
            }
            const presenter = form.querySelector('[data-presenter-fields]');
            const image = form.querySelector('[data-image-fields]');
            function sync() {
                const checked = form.querySelector('[data-style-input]:checked');
                if (presenter) { presenter.hidden = !checked || checked.dataset.usesPresenter !== '1'; }
                if (image) { image.hidden = !checked || checked.dataset.usesImage !== '1'; }
            }
            form.querySelectorAll('[data-style-input]').forEach(function (input) { input.addEventListener('change', sync); });
            sync();

            const script = form.querySelector('[data-quick-script]');
            const meter = form.querySelector('[data-quick-meter]');
            function measure() {
                if (!script || !meter) { return; }
                const words = script.value.trim() ? script.value.trim().split(/\s+/).length : 0;
                meter.textContent = words ? words + ' words · ~' + Math.round(words / 2.6) + 's spoken' : '';
            }
            if (script) { script.addEventListener('input', measure); measure(); }

            form.addEventListener('submit', function () {
                const button = form.querySelector('[data-quick-submit]');
                if (button) { button.disabled = true; button.lastChild.textContent = ' Starting…'; }
            });
        })();
    </script>
@endpush

{{-- UGC video, step by step: angle → character → script → edit & render.
     Inputs from several steps belong to one form via the HTML `form`
     attribute, so each step can live in its own card. --}}
@php
    $ugcGens = $generations;
    $latestAngles = $ugcGens->where('module', 'ugc_angles')->where('status', 'completed')->first();
    $anglesPending = $ugcGens->where('module', 'ugc_angles')->where('status', 'queued')->isNotEmpty();
    $scripts = $ugcGens->where('module', 'ugc_content')->where('status', 'completed')->values();
    $scriptPending = $ugcGens->where('module', 'ugc_content')->where('status', 'queued')->isNotEmpty();
    $selectedScript = $scripts->firstWhere('id', (int) request('script')) ?? $scripts->first();
    $scriptVideo = $selectedScript
        ? $ugcGens->where('module', 'ugc_video')->first(fn ($g) => ($g->input['content_generation_id'] ?? null) === $selectedScript->id)
        : null;
    $canRenderVideo = ! auth()->user()->isSeat();
    $chosenAvatar = $selectedScript->input['avatar_id'] ?? old('avatar_id') ?? ($ugcAvatars[0]['id'] ?? null);
    $chosenVoice = $selectedScript->input['voice_id'] ?? old('voice_id') ?? ($ugcVoices[0]['id'] ?? null);
    $editableScript = $selectedScript ? ($selectedScript->output_meta['edited_video_script'] ?? $selectedScript->output_meta['video_script'] ?? '') : '';
    $maxChars = \App\Http\Controllers\Dashboard\UgcController::MAX_VIDEO_SCRIPT_CHARACTERS;
    $stepDone = [1 => (bool) $latestAngles, 2 => (bool) $selectedScript, 3 => (bool) $selectedScript, 4 => $scriptVideo && $scriptVideo->status === 'completed'];
@endphp

<form id="ugc-script-form" method="POST" action="{{ route('offers.ugc.content', $offer) }}" hidden>
    @csrf
    @if ($latestAngles)
        <input type="hidden" name="angles_generation_id" value="{{ $latestAngles->id }}">
    @endif
    <input type="hidden" name="avatar_name" data-avatar-name>
    <input type="hidden" name="voice_name" data-voice-name>
</form>

<ol class="space-y-4" data-ugc-stepper>
    {{-- Step 1: angle --}}
    <li class="rounded-3xl border border-line bg-white p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-start gap-3">
                @include('dashboard.offers.features._step-badge', ['number' => 1, 'done' => $stepDone[1]])
                <div>
                    <h3 class="font-display text-lg font-semibold text-navy-900">Choose your angle</h3>
                    <p class="text-sm text-ink-600">The creative idea behind the video. The AI suggests five; pick the one that feels most like you.</p>
                </div>
            </div>
            @if ($latestAngles && ! $anglesPending)
                <form method="POST" action="{{ route('offers.ugc.angles', $offer) }}">
                    @csrf
                    <x-ui.button variant="ghost" size="sm" icon="arrow-path">New angles ({{ config('credits.costs.ugc_angles') }})</x-ui.button>
                </form>
            @endif
        </div>

        @if ($anglesPending)
            <p class="mt-4 flex items-center gap-2 rounded-2xl bg-rose-50 p-4 text-sm text-rose-800"><x-heroicon-m-sparkles class="h-5 w-5 animate-pulse" /> Brainstorming five angles for {{ $offer->product_name }}… this page updates by itself.</p>
        @elseif (! $latestAngles)
            <form method="POST" action="{{ route('offers.ugc.angles', $offer) }}" class="mt-4">
                @csrf
                <x-ui.button variant="primary" icon="light-bulb" size="lg">Suggest 5 angles ({{ config('credits.costs.ugc_angles') }} credits)</x-ui.button>
            </form>
        @else
            <fieldset class="mt-4">
                <legend class="sr-only">UGC angle</legend>
                <div class="grid gap-2.5 md:grid-cols-2">
                    @foreach ($latestAngles->output_meta['angles'] ?? [] as $index => $angle)
                        <label class="group relative flex cursor-pointer gap-3 rounded-2xl bg-white p-3.5 ring-1 ring-line transition hover:ring-rose-300 has-[:checked]:bg-rose-50/60 has-[:checked]:ring-2 has-[:checked]:ring-rose-500">
                            <input type="radio" name="angle_index" value="{{ $index }}" form="ugc-script-form" required @checked((string) old('angle_index', $selectedScript && ($selectedScript->input['angles_generation_id'] ?? null) === $latestAngles->id ? ($selectedScript->input['angle_index'] ?? 0) : 0) === (string) $index)
                                   class="mt-1 h-4 w-4 shrink-0 accent-rose-600">
                            <span class="min-w-0">
                                <span class="flex flex-wrap items-center gap-1.5 font-semibold text-navy-900">
                                    {{ $angle['name'] ?? '' }}
                                    <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-rose-700">{{ $angle['format'] ?? '' }}</span>
                                </span>
                                <span class="mt-1 block text-sm text-ink-900">&ldquo;{{ trim((string) ($angle['hook_idea'] ?? ''), " \"'“”‘’") }}&rdquo;</span>
                                <span class="mt-1 block text-xs text-ink-600">{{ $angle['why_it_works'] ?? '' }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endif
    </li>

    {{-- Step 2: character --}}
    <li class="rounded-3xl border border-line bg-white p-5 sm:p-6">
        <div class="flex items-start gap-3">
            @include('dashboard.offers.features._step-badge', ['number' => 2, 'done' => $stepDone[2] && $ugcVideoReady])
            <div>
                <h3 class="font-display text-lg font-semibold text-navy-900">Choose your character</h3>
                <p class="text-sm text-ink-600">The AI presenter who appears in your video, and the voice they speak with.</p>
            </div>
        </div>

        @if (! $ugcVideoReady)
            <div class="mt-4 flex items-start gap-3 rounded-2xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-200">
                <x-heroicon-o-wrench-screwdriver class="h-5 w-5 shrink-0" />
                <p><strong>AI presenters are being set up.</strong> Our team still needs to connect the video engine (HeyGen) in the admin panel. You can still generate and edit your script now — video creation switches on automatically once it's connected.</p>
            </div>
        @elseif ($ugcAvatars === [] || $ugcVoices === [])
            <p class="mt-4 rounded-2xl bg-surface-muted p-4 text-sm text-ink-600">The presenter list is temporarily unavailable — refresh in a minute. You can still generate your script.</p>
        @else
            <div class="mt-4 flex flex-wrap items-center gap-2" data-avatar-filter>
                <span class="text-xs font-medium text-ink-600">Show:</span>
                @foreach (['all' => 'Everyone', 'female' => 'Women', 'male' => 'Men'] as $filterKey => $filterLabel)
                    <button type="button" data-gender="{{ $filterKey }}" aria-pressed="{{ $filterKey === 'all' ? 'true' : 'false' }}"
                            class="rounded-full px-3 py-1 text-xs font-medium ring-1 ring-line transition aria-pressed:bg-rose-600 aria-pressed:text-white aria-pressed:ring-rose-600">{{ $filterLabel }}</button>
                @endforeach
            </div>
            <fieldset class="mt-3">
                <legend class="sr-only">Presenter</legend>
                <div class="grid max-h-[26rem] grid-cols-2 gap-2.5 overflow-y-auto p-0.5 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($ugcAvatars as $avatar)
                        <label data-avatar-card data-gender-value="{{ strtolower((string) $avatar['gender']) }}"
                               class="group relative cursor-pointer overflow-hidden rounded-2xl bg-surface-muted ring-1 ring-line transition hover:ring-rose-300 has-[:checked]:ring-2 has-[:checked]:ring-rose-500">
                            <input type="radio" name="avatar_id" value="{{ $avatar['id'] }}" form="ugc-script-form" data-avatar-input data-name="{{ $avatar['name'] }}" @checked($chosenAvatar === $avatar['id']) class="peer sr-only">
                            @if ($avatar['preview_image_url'])
                                <img src="{{ $avatar['preview_image_url'] }}" alt="" loading="lazy" class="aspect-[3/4] w-full object-cover">
                            @else
                                <span class="flex aspect-[3/4] w-full items-center justify-center bg-gradient-to-br from-rose-200 to-violet-300 font-display text-2xl font-semibold text-white">{{ mb_strtoupper(mb_substr($avatar['name'], 0, 1)) }}</span>
                            @endif
                            <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-navy-950/85 to-transparent px-2.5 pb-2 pt-6 text-xs font-medium text-white">{{ $avatar['name'] }}</span>
                            <span class="absolute right-2 top-2 hidden h-6 w-6 items-center justify-center rounded-full bg-rose-600 text-white peer-checked:inline-flex"><x-heroicon-m-check class="h-4 w-4" /></span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <div class="mt-4 max-w-md">
                <label for="ugc-voice" class="block text-sm font-medium text-navy-900 mb-1.5">Voice</label>
                <select id="ugc-voice" name="voice_id" form="ugc-script-form" data-voice-input class="w-full rounded-xl border border-line bg-white px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                    @foreach (collect($ugcVoices)->sortBy('language')->groupBy(fn ($voice) => $voice['language'] ?: 'Other') as $language => $languageVoices)
                        <optgroup label="{{ $language }}">
                            @foreach ($languageVoices as $voice)
                                <option value="{{ $voice['id'] }}" @selected($chosenVoice === $voice['id'])>{{ $voice['name'] }}{{ $voice['gender'] ? ' · '.ucfirst($voice['gender']) : '' }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
        @endif
    </li>

    {{-- Step 3: script --}}
    <li class="rounded-3xl border border-line bg-white p-5 sm:p-6">
        <div class="flex items-start gap-3">
            @include('dashboard.offers.features._step-badge', ['number' => 3, 'done' => $stepDone[3]])
            <div>
                <h3 class="font-display text-lg font-semibold text-navy-900">Generate the script</h3>
                <p class="text-sm text-ink-600">A filming script, a clean script for the AI presenter to read, and captions for every platform — all written for your chosen angle.</p>
            </div>
        </div>
        @if ($scriptPending)
            <p class="mt-4 flex items-center gap-2 rounded-2xl bg-rose-50 p-4 text-sm text-rose-800"><x-heroicon-m-sparkles class="h-5 w-5 animate-pulse" /> Writing your script… this page updates by itself.</p>
        @else
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <x-ui.button variant="primary" size="lg" icon="pencil-square" form="ugc-script-form" :disabled="! $latestAngles || $anglesPending">
                    {{ $selectedScript ? 'Generate another script' : 'Generate script' }} ({{ config('credits.costs.ugc_content') }} credits)
                </x-ui.button>
                @if (! $latestAngles)
                    <span class="text-sm text-ink-400">Get your angles in step 1 first.</span>
                @elseif ($anglesPending)
                    <span class="text-sm text-ink-400">New angles are on their way — pick one when they arrive.</span>
                @endif
            </div>
        @endif
    </li>

    {{-- Step 4: edit & create video --}}
    <li id="step-video" class="scroll-mt-24 rounded-3xl border border-line bg-white p-5 sm:p-6">
        <div class="flex items-start gap-3">
            @include('dashboard.offers.features._step-badge', ['number' => 4, 'done' => $stepDone[4]])
            <div>
                <h3 class="font-display text-lg font-semibold text-navy-900">Edit your script &amp; create the video</h3>
                <p class="text-sm text-ink-600">Change any word you like — the presenter reads exactly what's in the box.</p>
            </div>
        </div>

        @if (! $selectedScript)
            <p class="mt-4 rounded-2xl bg-surface-muted p-4 text-sm text-ink-600">Your editable script appears here after step 3.</p>
        @else
            @if ($scripts->count() > 1)
                <form method="GET" action="{{ route('offers.feature', [$offer, 'ugc']) }}" class="mt-4 flex flex-wrap items-center gap-2">
                    <label for="ugc-script-pick" class="text-xs font-medium text-ink-600">Script:</label>
                    <select id="ugc-script-pick" name="script" onchange="this.form.submit()" class="rounded-full border border-line bg-white px-3 py-1.5 text-xs">
                        @foreach ($scripts as $scriptOption)
                            @php($optionAngle = $offer->generations->firstWhere('id', $scriptOption->input['angles_generation_id'] ?? null)?->output_meta['angles'][$scriptOption->input['angle_index'] ?? 0]['name'] ?? 'Script')
                            <option value="{{ $scriptOption->id }}" @selected($scriptOption->id === $selectedScript->id)>{{ $optionAngle }} · {{ $scriptOption->created_at->diffForHumans() }}</option>
                        @endforeach
                    </select>
                    <noscript><button class="rounded-full border border-line px-3 py-1.5 text-xs">Switch</button></noscript>
                </form>
            @endif

            <form method="POST" action="{{ route('offers.ugc.video', $offer) }}" class="mt-4" data-ugc-video-form>
                @csrf
                <input type="hidden" name="content_generation_id" value="{{ $selectedScript->id }}">
                <label for="video_script" class="flex flex-wrap items-center justify-between gap-x-3 text-sm font-medium text-navy-900">
                    <span>What your presenter will say</span>
                    <span class="text-xs font-normal text-ink-400" data-script-meter></span>
                </label>
                <textarea id="video_script" name="video_script" rows="9" maxlength="{{ $maxChars }}" data-script-editor
                          class="mt-1.5 w-full rounded-2xl border border-line bg-white px-4 py-3 text-sm leading-relaxed focus:outline-none focus:ring-2 focus:ring-rose-500">{{ old('video_script', $editableScript) }}</textarea>
                <p class="mt-1 text-xs text-ink-400">Plain spoken sentences work best — no headings or stage directions. Keep it under ~60 seconds ({{ $maxChars }} characters).</p>

                @if ($ugcVideoReady && $ugcAvatars !== [] && $ugcVoices !== [])
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="video-avatar" class="block text-xs font-medium text-ink-600 mb-1">Presenter</label>
                            <select id="video-avatar" name="avatar_id" required data-video-avatar class="w-full rounded-xl border border-line bg-white px-3 py-2 text-sm">
                                @foreach ($ugcAvatars as $avatar)
                                    <option value="{{ $avatar['id'] }}" @selected($chosenAvatar === $avatar['id'])>{{ $avatar['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="video-voice" class="block text-xs font-medium text-ink-600 mb-1">Voice</label>
                            <select id="video-voice" name="voice_id" required data-video-voice class="w-full rounded-xl border border-line bg-white px-3 py-2 text-sm">
                                @foreach ($ugcVoices as $voice)
                                    <option value="{{ $voice['id'] }}" @selected($chosenVoice === $voice['id'])>{{ $voice['name'] }}{{ $voice['language'] ? ' · '.$voice['language'] : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endif

                <div class="mt-5 flex flex-col gap-3 rounded-2xl bg-gradient-to-r from-rose-50 to-white p-4 ring-1 ring-rose-100 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-ink-900">
                        <span class="inline-flex items-center gap-1 font-semibold"><x-heroicon-m-bolt class="h-4 w-4 text-gold-500" /> {{ config('credits.costs.ugc_video') }} credits</span>
                        <span class="text-ink-600">· renders in a few minutes · 9:16 HD</span>
                    </p>
                    @if (! $canRenderVideo)
                        <p class="text-sm text-ink-600">Ask your account owner to render the video — team seats can write and edit scripts.</p>
                    @elseif (! $ugcVideoReady)
                        <x-ui.button variant="secondary" disabled title="Video creation needs the admin to connect HeyGen">Create video (coming soon)</x-ui.button>
                    @elseif ($ugcAvatars === [] || $ugcVoices === [])
                        <x-ui.button variant="secondary" disabled>Presenters unavailable</x-ui.button>
                    @elseif ($scriptVideo && $scriptVideo->status === 'queued')
                        <span class="inline-flex items-center gap-2 text-sm font-medium text-rose-700"><x-heroicon-m-sparkles class="h-4 w-4 animate-pulse" /> Rendering your video…</span>
                    @else
                        <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-full bg-gradient-to-r from-rose-500 to-pink-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-rose-500/25 transition hover:from-rose-600 hover:to-pink-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-rose-500">
                            <x-heroicon-m-video-camera class="h-5 w-5" /> {{ $scriptVideo ? 'Create another video' : 'Create video' }}
                        </button>
                    @endif
                </div>
            </form>

            @if ($scriptVideo)
                <p class="mt-3 text-sm text-ink-600">
                    @if ($scriptVideo->status === 'completed')
                        <a href="#generation-{{ $scriptVideo->id }}" class="font-medium text-rose-600 hover:text-rose-700">Your video from this script is ready below &darr;</a>
                    @elseif ($scriptVideo->status === 'failed')
                        The last video from this script failed: {{ $scriptVideo->error_message }}
                    @endif
                </p>
            @endif
        @endif
    </li>
</ol>

@push('scripts')
    <script>
        (function () {
            const root = document.querySelector('[data-ugc-stepper]');
            if (!root) {
                return;
            }
            const avatarName = document.querySelector('[data-avatar-name]');
            const voiceName = document.querySelector('[data-voice-name]');
            const voiceInput = root.querySelector('[data-voice-input]');
            const videoAvatar = root.querySelector('[data-video-avatar]');
            const videoVoice = root.querySelector('[data-video-voice]');

            function syncNames() {
                const checked = root.querySelector('[data-avatar-input]:checked');
                if (avatarName) { avatarName.value = checked ? checked.dataset.name : ''; }
                if (voiceName && voiceInput) { voiceName.value = voiceInput.options[voiceInput.selectedIndex]?.text || ''; }
            }

            root.querySelectorAll('[data-avatar-input]').forEach(function (input) {
                input.addEventListener('change', function () {
                    if (videoAvatar) { videoAvatar.value = input.value; }
                    syncNames();
                });
            });
            if (voiceInput) {
                voiceInput.addEventListener('change', function () {
                    if (videoVoice) { videoVoice.value = voiceInput.value; }
                    syncNames();
                });
            }
            syncNames();

            root.querySelectorAll('[data-avatar-filter] [data-gender]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const gender = button.dataset.gender;
                    root.querySelectorAll('[data-avatar-filter] [data-gender]').forEach(function (other) {
                        other.setAttribute('aria-pressed', other === button ? 'true' : 'false');
                    });
                    root.querySelectorAll('[data-avatar-card]').forEach(function (card) {
                        card.hidden = gender !== 'all' && card.dataset.genderValue !== gender;
                    });
                });
            });

            const editor = root.querySelector('[data-script-editor]');
            const meter = root.querySelector('[data-script-meter]');
            function measure() {
                if (!editor || !meter) {
                    return;
                }
                const words = editor.value.trim() ? editor.value.trim().split(/\s+/).length : 0;
                const seconds = Math.round(words / 2.6);
                meter.textContent = words + ' words · ~' + seconds + 's · ' + editor.value.length + '/' + editor.maxLength + ' characters';
                meter.classList.toggle('text-red-600', seconds > 65);
            }
            if (editor) {
                editor.addEventListener('input', measure);
                measure();
            }
        })();
    </script>
@endpush

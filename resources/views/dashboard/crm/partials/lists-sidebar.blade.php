{{-- The CRM's lists: one per researched product (auto-created) plus custom
     lists, each with its colour and member count. --}}
@php($productLists = $lists->whereNotNull('offer_id'))
@php($customLists = $lists->whereNull('offer_id'))
<nav class="rounded-2xl border border-line bg-surface p-2 shadow-[0_1px_2px_rgba(16,24,40,0.04)]">
    <a href="{{ route('crm.index') }}" @class([
        'flex items-center justify-between gap-2 rounded-xl px-3 py-2 text-sm transition',
        'bg-navy-900 text-white' => ! $activeList && request('list') !== 'none',
        'text-ink-900 hover:bg-surface-muted' => $activeList || request('list') === 'none',
    ])>
        <span class="flex items-center gap-2"><x-heroicon-o-users class="h-4 w-4" /> All contacts</span>
    </a>

    @foreach ([['Product lists', $productLists, 'cube'], ['My lists', $customLists, 'tag']] as [$heading, $group, $icon])
        <p class="mt-3 mb-1 flex items-center gap-1.5 px-3 text-[11px] font-mono uppercase tracking-[0.14em] text-ink-400">
            <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-3.5 w-3.5" /> {{ $heading }}
        </p>
        @forelse ($group as $list)
            @php($isActive = $activeList && $activeList->id === $list->id)
            <a href="{{ route('crm.index', ['list' => $list->id]) }}" @class([
                'group flex items-center justify-between gap-2 rounded-xl px-3 py-2 text-sm transition',
                'bg-brand-50 text-brand-700 font-medium' => $isActive,
                'text-ink-900 hover:bg-surface-muted' => ! $isActive,
            ]) @if ($isActive) aria-current="page" @endif>
                <span class="flex min-w-0 items-center gap-2">
                    <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $list->dotClass() }}" aria-hidden="true"></span>
                    <span class="truncate">{{ $list->name }}</span>
                </span>
                <span class="shrink-0 rounded-full bg-surface-muted px-2 py-0.5 text-[11px] tabular-nums text-ink-600 group-hover:bg-white">{{ number_format($list->contacts_count) }}</span>
            </a>
        @empty
            <p class="px-3 py-1.5 text-xs text-ink-400">
                {{ $heading === 'Product lists' ? 'Research a product and its list appears here.' : 'None yet.' }}
            </p>
        @endforelse
    @endforeach

    <a href="{{ route('crm.index', ['list' => 'none']) }}" @class([
        'mt-2 flex items-center gap-2 rounded-xl px-3 py-2 text-xs transition',
        'bg-brand-50 text-brand-700' => request('list') === 'none',
        'text-ink-600 hover:bg-surface-muted' => request('list') !== 'none',
    ])>
        <x-heroicon-o-question-mark-circle class="h-4 w-4" /> Not in any list
    </a>

    <details class="mt-2 border-t border-line pt-2" @if ($errors->has('name') && old('_list_form')) open @endif>
        <summary class="flex cursor-pointer list-none items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium text-brand-600 hover:bg-brand-50">
            <x-heroicon-m-plus class="h-4 w-4" /> New list
        </summary>
        <form method="POST" action="{{ route('crm.lists.store') }}" class="space-y-2 px-3 pb-2 pt-1">
            @csrf
            <input type="hidden" name="_list_form" value="1">
            <label class="block">
                <span class="sr-only">List name</span>
                <input name="name" required maxlength="100" placeholder="e.g. Lagos dentists" class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            </label>
            <fieldset>
                <legend class="sr-only">Colour</legend>
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($listColors as $i => $color)
                        @php($swatch = (new \App\Models\CrmList(['color' => $color]))->dotClass())
                        <label class="cursor-pointer">
                            <input type="radio" name="color" value="{{ $color }}" class="peer sr-only" @checked($i === 0)>
                            <span class="block h-6 w-6 rounded-full {{ $swatch }} ring-2 ring-transparent ring-offset-2 peer-checked:ring-navy-900 peer-focus-visible:ring-brand-500" title="{{ ucfirst($color) }}"></span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <x-ui.button size="sm" class="w-full" icon="plus">Create list</x-ui.button>
        </form>
    </details>
</nav>

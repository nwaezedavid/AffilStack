@extends('layouts.app')

@section('title', 'Import contacts')

@section('content')
    <x-ui.page-header title="Match your columns" eyebrow="CSV import · step 2 of 2" icon="table-cells"
        subtitle="Tell us what each column in your file holds. Nothing is saved until you click Import." />

    <form method="POST" action="{{ route('crm.import.store', $token) }}" class="space-y-6">
        @csrf

        <x-ui.card title="Import into" icon="queue-list" description="Contacts already in your CRM (same email) are simply added to this list — never duplicated.">
            <label class="block max-w-md">
                <span class="sr-only">List</span>
                <select name="list_id" required class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                    @foreach ($lists as $list)
                        <option value="{{ $list->id }}" @selected($selectedList === $list->id)>{{ $list->name }}</option>
                    @endforeach
                </select>
            </label>
        </x-ui.card>

        <x-ui.card title="Columns" icon="arrows-right-left" :description="number_format($rowCount).' row(s) found. Preview of the first '.count($sample).':'" padding="p-0">
            <div class="relative overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-line bg-surface-muted/60">
                            @foreach ($headers as $i => $header)
                                <th class="min-w-[11rem] px-3 py-3 text-left align-top">
                                    <span class="block truncate font-mono text-[11px] uppercase tracking-[0.12em] text-ink-400" title="{{ $header }}">{{ $header !== '' ? $header : 'Column '.($i + 1) }}</span>
                                    <label class="mt-1.5 block">
                                        <span class="sr-only">Map {{ $header }} to</span>
                                        <select name="mapping[{{ $i }}]" @class([
                                            'w-full rounded-lg border px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500',
                                            'border-brand-500 bg-brand-50 text-brand-700 font-medium' => $mapping[$i],
                                            'border-line bg-white text-ink-600' => ! $mapping[$i],
                                        ])>
                                            <option value="">Skip this column</option>
                                            @foreach ($fields as $field)
                                                <option value="{{ $field }}" @selected($mapping[$i] === $field)>{{ ucwords(str_replace('_', ' ', $field)) }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($sample as $row)
                            <tr>
                                @foreach ($headers as $i => $header)
                                    <td class="px-3 py-2.5 text-ink-600"><span class="block max-w-[14rem] truncate">{{ $row[$i] ?? '' }}</span></td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.card>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('crm.index') }}" class="text-sm text-ink-600 hover:text-ink-900">Cancel</a>
            <x-ui.button variant="brand" size="lg" icon="arrow-up-tray">Import {{ number_format($rowCount) }} row{{ $rowCount === 1 ? '' : 's' }}</x-ui.button>
        </div>
    </form>
@endsection

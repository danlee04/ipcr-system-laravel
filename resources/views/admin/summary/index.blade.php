@php
    // One chip per number, and the number and its word sit together on purpose:
    // "4 employees" reads as a fact, "4" above "employees" reads as a dashboard.
    $chip = 'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset';
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Period Summary') }}</h2>
    </x-slot>

    <x-page-container class="space-y-6">
        <x-admin.flash />

        <p class="max-w-3xl text-sm text-gray-600">
            The whole hospital for one rating period, gathered by division and section. Built from the roll of
            employees rather than from the IPCRs, so the people who never started one are on it too — they are usually
            who you are looking for. Only approved ratings are averaged.
        </p>

        @if ($period === null)
            <div class="rounded-md bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-500/20">
                No rating period has been set up yet. Create one under Rating Periods and this sheet will fill itself.
            </div>
        @else
            {{-- A plain GET form: the query string is the only state, so a
                 filtered sheet can be bookmarked and handed to someone else. --}}
            <form method="GET" action="{{ route('admin.summary.index') }}" class="flex flex-wrap items-end gap-2">
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-gray-600">Rating period</span>
                    <select name="period" class="w-56 rounded-lg border-gray-300 text-sm">
                        @foreach ($periods as $option)
                            <option value="{{ $option->id }}" @selected($option->id === $period->id)>{{ $option->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-gray-600">Division</span>
                    <select name="division" class="w-48 rounded-lg border-gray-300 text-sm">
                        <option value="">All divisions</option>
                        @foreach ($divisions as $division)
                            <option value="{{ $division->id }}" @selected(request('division') == $division->id)>
                                {{ $division->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-gray-600">Section</span>
                    <select name="section" class="w-48 rounded-lg border-gray-300 text-sm">
                        <option value="">All sections</option>
                        @foreach ($sections as $section)
                            <option value="{{ $section->id }}" @selected(request('section') == $section->id)>
                                {{ $section->name }}</option>
                        @endforeach
                    </select>
                </label>

                <button type="submit"
                    class="rounded-lg bg-nav-900 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-nav-800">
                    Show
                </button>

                <a href="{{ route('admin.summary.export', request()->only(['period', 'division', 'section'])) }}"
                    class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 transition-colors hover:bg-gray-50">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v11m0 0 4-4m-4 4-4-4M5 19h14" />
                    </svg>
                    Download CSV
                </a>
            </form>

            {{-- The hospital's own line. Every heading below reads the same
                 way, so one habit covers the whole sheet. --}}
            <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
                <h3 class="text-base font-semibold text-gray-900">{{ $period->name }}</h3>
                <p class="mt-0.5 text-sm text-gray-600">
                    {{ $period->start_date?->format('d M Y') }} – {{ $period->end_date?->format('d M Y') }}
                </p>
                <x-admin.summary-tally :tally="$overall" size="lg" class="mt-3" />
            </div>

            @forelse ($gathered as $divisionName => $sections)
                @php $inDivision = $sections->flatten(1); @endphp

                <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-950/5">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-6 py-4">
                        <h3 class="text-sm font-semibold text-gray-900">{{ $divisionName }}</h3>
                        <x-admin.summary-tally :tally="\App\Support\SummaryTally::of($inDivision)" />
                    </div>

                    @foreach ($sections as $sectionName => $rows)
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-6 py-3">
                            <h4 class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $sectionName }}
                            </h4>
                            <x-admin.summary-tally :tally="\App\Support\SummaryTally::of($rows)" />
                        </div>

                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-white">
                                <tr>
                                    <th
                                        class="px-6 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-400">
                                        Employee</th>
                                    <th
                                        class="px-6 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-400">
                                        Position</th>
                                    <th
                                        class="px-6 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-400">
                                        Status</th>
                                    <th
                                        class="px-6 py-2 text-right text-xs font-medium uppercase tracking-wider text-gray-400">
                                        Rating</th>
                                    <th
                                        class="px-6 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-400">
                                        Adjectival</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($rows as $row)
                                    <tr>
                                        <td class="px-6 py-3 text-sm">
                                            <span
                                                class="block font-medium text-gray-900">{{ $row->employee->full_name }}</span>
                                            @if ($row->employee->employee_number)
                                                <span
                                                    class="block font-data text-xs text-gray-500">{{ $row->employee->employee_number }}</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-3 text-sm text-gray-600">
                                            {{ $row->employee->position?->title ?? '—' }}</td>
                                        <td class="px-6 py-3 text-sm">
                                            @if ($row->status())
                                                <span
                                                    class="{{ $chip }} {{ $row->status()->badgeClasses() }}">{{ $row->statusLabel() }}</span>
                                            @else
                                                <span
                                                    class="{{ $chip }} bg-gray-100 text-gray-600 ring-gray-500/20">Not started</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-3 text-right font-data text-sm text-gray-900">
                                            {{ $row->approvedRating() === null ? '—' : number_format($row->approvedRating(), 2) }}
                                        </td>
                                        <td class="px-6 py-3 text-sm text-gray-600">
                                            {{ $row->isApproved() ? ($row->ipcr->final_adjectival_rating ?? '—') : '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endforeach
                </div>
            @empty
                <div class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 shadow-sm ring-1 ring-gray-950/5">
                    No active employees match these filters.
                </div>
            @endforelse
        @endif
    </x-page-container>
</x-app-layout>

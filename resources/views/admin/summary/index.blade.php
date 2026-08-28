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
        @if ($period === null)
            <div class="rounded-md bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-500/20">
                No rating period has been set up yet. Create one under Rating Periods and this sheet will fill itself.
            </div>
        @else
            <x-admin.live-list :action="route('admin.summary.index')">
                {{-- A plain GET form: the query string is the only state, so a
                 filtered sheet can be bookmarked and handed to someone else.
                 `data-live-form` is what makes it answer as you choose. --}}
                <form method="GET" action="{{ route('admin.summary.index') }}" data-live-form
                    class="flex flex-wrap items-end gap-2">
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

                    {{-- Outside the rows, so a live filter would otherwise leave
                     it pointing at the filters the page arrived with and hand
                     over the wrong sheet. `data-live-export` carries the base
                     URL; liveList rewrites the rest after every change. --}}
                    <a href="{{ route('admin.summary.export', request()->only(['period', 'division', 'section'])) }}"
                        data-live-export="{{ route('admin.summary.export') }}"
                        class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 transition-colors hover:bg-gray-50">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 4v11m0 0 4-4m-4 4-4-4M5 19h14" />
                        </svg>
                        Download CSV
                    </a>
                </form>

                <x-admin.live-results>
                    @include('admin.summary.rows')
                </x-admin.live-results>
            </x-admin.live-list>
        @endif
    </x-page-container>
</x-app-layout>

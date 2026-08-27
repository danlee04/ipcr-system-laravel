<x-app-layout>
    <x-slot name="header">
        {{-- The primary action sits beside the title, as it does on every
             other admin screen. Which thing it creates follows the open tab. --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Positions') }}</h2>
            <button type="button" x-data
                x-on:click="$dispatch('open-modal', '{{ $tab === 'positions' ? 'create-position' : 'create-designation' }}')"
                class="inline-flex items-center rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-nav-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-seal focus-visible:ring-offset-2">
                + New {{ $tab === 'positions' ? 'Position' : 'Designation' }}
            </button>
        </div>
    </x-slot>

    <x-page-container class="space-y-6">
        <x-admin.flash />

        <p class="max-w-3xl text-sm text-gray-600">
            A <strong>position</strong> is the single plantilla post an employee holds, and the source of their
            core functions. A <strong>designation</strong> is an extra assignment they may hold several of at once,
            and the source of strategic and support functions.
        </p>

        {{-- Tab state lives in the query string so a redirect after saving
             returns to the tab the administrator was working in. --}}
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex gap-6" aria-label="Job title type">
                <a href="{{ route('admin.positions.index') }}"
                    class="border-b-2 px-1 pb-3 text-sm font-medium {{ $tab === 'positions' ? 'border-nav-900 text-nav-900' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    Positions ({{ $positionCount }})
                </a>
                <a href="{{ route('admin.positions.index', ['tab' => 'designations']) }}"
                    class="border-b-2 px-1 pb-3 text-sm font-medium {{ $tab === 'designations' ? 'border-nav-900 text-nav-900' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    Designations ({{ $designationCount }})
                </a>
            </nav>
        </div>

        {{-- The two tabs do not take the same filters. A position sits in a
             section and so in a division; a designation sits nowhere, and
             offering it those would invite a search that can only come back
             empty. --}}
        <x-admin.live-list :action="route('admin.positions.index')">
        <x-admin.filter-bar :action="route('admin.positions.index')"
            :placeholder="$tab === 'positions' ? 'Search by title or item number' : 'Search by title'"
            :hidden="$tab === 'designations' ? ['tab' => 'designations'] : []">

            @if ($tab === 'positions')
                <div class="flex flex-wrap items-end gap-2"
                    x-data="{ division: '{{ request('division') }}', section: '{{ request('section') }}' }">
                    <label class="block">
                        <span class="sr-only">Division</span>
                        <select name="division" x-model="division" x-on:change="section = ''"
                            class="w-44 rounded-lg border-gray-300 text-sm">
                            <option value="">All divisions</option>
                            @foreach ($divisions as $option)
                                <option value="{{ $option->id }}">{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block">
                        <span class="sr-only">Section</span>
                        <select name="section" x-model="section" class="w-44 rounded-lg border-gray-300 text-sm">
                            <option value="">All sections</option>
                            @foreach ($sections as $option)
                                <option value="{{ $option->id }}" data-division="{{ $option->division_id }}"
                                    x-show="division === '' || division === '{{ $option->division_id }}'">
                                    {{ $option->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                </div>
            @endif

            <label class="block">
                <span class="sr-only">Status</span>
                <select name="status" class="w-32 rounded-lg border-gray-300 text-sm">
                    <option value="">Any status</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                </select>
            </label>
        </x-admin.filter-bar>

        <x-admin.live-results>
            @include('admin.positions.rows')
        </x-admin.live-results>
        </x-admin.live-list>
    </x-page-container>
</x-app-layout>

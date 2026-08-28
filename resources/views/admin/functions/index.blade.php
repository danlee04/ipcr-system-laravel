<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Functions') }}</h2>
            <button type="button" x-data x-on:click="$dispatch('open-modal', 'create-function')"
                class="inline-flex items-center rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-nav-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2">
                + New Function
            </button>
        </div>
    </x-slot>

    <x-page-container class="space-y-6">
        <x-admin.flash />

        {{-- No standing paragraph explaining the categories. The badges and
             the "Applies to" column say the same thing on every row, and a
             page you read daily should not re-teach itself every visit. --}}

        {{-- Division narrows Section, Section narrows Position: the three
             selects can never describe a combination that has no rows. --}}
        <x-admin.live-list :action="route('admin.functions.index')">
        <x-admin.filter-bar :action="route('admin.functions.index')"
            placeholder="Search by output or success indicator">
            <label class="block">
                <span class="sr-only">Category</span>
                <select name="category" class="w-44 rounded-lg border-gray-300 text-sm">
                    <option value="">All categories</option>

                    {{-- Not a category, and first, because it is the block
                         at the top of the list. --}}
                    <option value="common" @selected(request('category') === 'common')>Common Function</option>

                    @foreach (\App\Enums\FunctionCategory::cases() as $option)
                        <option value="{{ $option->value }}" @selected(request('category') === $option->value)>
                            {{ $option->label() }}</option>
                    @endforeach
                </select>
            </label>

            {{-- Narrowing clears what sat below it. A position from another
                 division stays selected otherwise - hidden by x-show but still
                 submitted, and the pair can only ever come back empty. --}}
            <div class="flex flex-wrap items-end gap-2"
                x-data="{
                    division: '{{ request('division') }}',
                    section: '{{ request('section') }}',
                    position: '{{ request('position') }}',
                }">
                <label class="block">
                    <span class="sr-only">Division</span>
                    <select name="division" x-model="division" x-on:change="section = ''; position = ''"
                        class="w-40 rounded-lg border-gray-300 text-sm">
                        <option value="">All divisions</option>
                        @foreach ($divisions as $division)
                            <option value="{{ $division->id }}">{{ $division->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="sr-only">Section</span>
                    <select name="section" x-model="section" x-on:change="position = ''"
                        class="w-44 rounded-lg border-gray-300 text-sm">
                        <option value="">All sections</option>
                        @foreach ($sections as $section)
                            <option value="{{ $section->id }}" data-division="{{ $section->division_id }}"
                                x-show="division === '' || division === '{{ $section->division_id }}'">
                                {{ $section->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="sr-only">Position</span>
                    <select name="position" x-model="position" class="w-44 rounded-lg border-gray-300 text-sm">
                        <option value="">All positions</option>
                        @foreach ($positions as $position)
                            <option value="{{ $position->id }}" data-section="{{ $position->section_id }}"
                                data-division="{{ $position->section?->division_id }}"
                                x-show="(section === '' || section === '{{ $position->section_id }}')
                                    && (division === '' || division === '{{ $position->section?->division_id }}')">
                                {{ $position->title }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </x-admin.filter-bar>

        <x-admin.live-results>
            @include('admin.functions.rows')
        </x-admin.live-results>
        </x-admin.live-list>

        <x-modal name="create-function" focusable max-width="4xl">
            <form method="POST" action="{{ route('admin.functions.store') }}" class="space-y-4 p-6">
                @csrf
                <h2 class="text-lg font-semibold text-gray-900">New function</h2>
                <x-admin.function-fields :positions="$positions" :designations="$designations"
                    :divisions="$divisions" :sections="$sections" />

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" x-on:click="$dispatch('close-modal', 'create-function')"
                        class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Cancel</button>
                    <button type="submit"
                        class="rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white hover:bg-nav-800">Add
                        function</button>
                </div>
            </form>
        </x-modal>
    </x-page-container>
</x-app-layout>

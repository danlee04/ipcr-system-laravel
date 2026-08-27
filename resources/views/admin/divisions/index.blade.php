<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Divisions') }}</h2>
            <div class="flex flex-wrap items-center gap-3">
                {{-- Asked of the whole hospital, not the page: a filter that
                     hides every division must not disable the button that
                     would let you add a section to one of them. --}}
                <button type="button" x-data x-on:click="$dispatch('open-modal', 'create-section')"
                    @disabled($allDivisions->isEmpty())
                    class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:text-gray-300">
                    + New Section
                </button>
                <button type="button" x-data x-on:click="$dispatch('open-modal', 'create-division')"
                    class="inline-flex items-center rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-nav-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-seal focus-visible:ring-offset-2">
                    + New Division
                </button>
            </div>
        </div>
    </x-slot>

    <x-page-container class="space-y-6">
        <x-admin.flash />

        @if ($employees->isEmpty())
            <div class="rounded-md bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-500/20">
                There are no employees yet, so no division head can be assigned. Until a head is set, nobody in
                the division can submit an IPCR.
            </div>
        @endif

        {{-- Division narrows Section, the same cascade as everywhere else, so
             the two filters can never describe a pair that has no rows. --}}
        <x-admin.live-list :action="route('admin.divisions.index')">
        <x-admin.filter-bar :action="route('admin.divisions.index')" placeholder="Search by name or code">
            <div class="flex flex-wrap items-end gap-2"
                x-data="{ division: '{{ request('division') }}', section: '{{ request('section') }}' }">
                <label class="block">
                    <span class="sr-only">Division</span>
                    <select name="division" x-model="division" x-on:change="section = ''"
                        class="w-48 rounded-lg border-gray-300 text-sm">
                        <option value="">All divisions</option>
                        @foreach ($allDivisions as $option)
                            <option value="{{ $option->id }}">{{ $option->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="sr-only">Section</span>
                    <select name="section" x-model="section" class="w-48 rounded-lg border-gray-300 text-sm">
                        <option value="">All sections</option>
                        @foreach ($allSections as $option)
                            <option value="{{ $option->id }}" data-division="{{ $option->division_id }}"
                                x-show="division === '' || division === '{{ $option->division_id }}'">
                                {{ $option->name }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>
        </x-admin.filter-bar>

        <x-admin.live-results>
            @include('admin.divisions.rows')
        </x-admin.live-results>
        </x-admin.live-list>

        <x-modal name="create-division" focusable max-width="lg">
            <form method="POST" action="{{ route('admin.divisions.store') }}" class="space-y-4 p-6">
                @csrf
                <h2 class="text-lg font-semibold text-gray-900">New division</h2>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700">Name</span>
                    <input type="text" name="name" required placeholder="Medical Services Division"
                        class="w-full rounded-md border-gray-300 text-sm">
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700">Code
                        <span class="text-gray-400">(optional)</span></span>
                    <input type="text" name="code" maxlength="20" placeholder="MED"
                        class="w-full rounded-md border-gray-300 text-sm">
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700">Division Head
                        <span class="text-gray-400">(optional)</span></span>
                    <select name="division_head_employee_id" class="w-full rounded-md border-gray-300 text-sm">
                        <option value="">Assign later</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" x-on:click="$dispatch('close-modal', 'create-division')"
                        class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit"
                        class="rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white hover:bg-nav-800">
                        Create division
                    </button>
                </div>
            </form>
        </x-modal>

        <x-modal name="create-section" focusable max-width="lg">
            <form method="POST" action="{{ route('admin.sections.store') }}" class="space-y-4 p-6">
                @csrf
                <h2 class="text-lg font-semibold text-gray-900">New section</h2>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700">Division</span>
                    <select name="division_id" required class="w-full rounded-md border-gray-300 text-sm">
                        <option value="">Select division…</option>
                        {{-- Every division in the hospital. The paged list
                             would shrink this to whatever is on screen. --}}
                        @foreach ($allDivisions as $division)
                            <option value="{{ $division->id }}">{{ $division->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700">Name</span>
                    <input type="text" name="name" required placeholder="Nursing Section"
                        class="w-full rounded-md border-gray-300 text-sm">
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700">Code
                        <span class="text-gray-400">(optional)</span></span>
                    <input type="text" name="code" maxlength="20" placeholder="NUR"
                        class="w-full rounded-md border-gray-300 text-sm">
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700">Section Head
                        <span class="text-gray-400">(optional)</span></span>
                    <select name="section_head_employee_id" class="w-full rounded-md border-gray-300 text-sm">
                        <option value="">Assign later</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" x-on:click="$dispatch('close-modal', 'create-section')"
                        class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit"
                        class="rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white hover:bg-nav-800">
                        Create section
                    </button>
                </div>
            </form>
        </x-modal>
    </x-page-container>
</x-app-layout>

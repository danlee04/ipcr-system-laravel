<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Employees') }}</h2>
            <button type="button" x-data x-on:click="$dispatch('open-modal', 'create-employee')"
                class="inline-flex items-center rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-nav-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2">
                + New Employee
            </button>
        </div>
    </x-slot>

    <x-page-container class="space-y-6">
        <x-admin.flash />

        <x-admin.live-list :action="route('admin.employees.index')">
        <x-admin.filter-bar :action="route('admin.employees.index')"
            placeholder="Search by name, employee number or email">
            <label class="block">
                <span class="sr-only">Division</span>
                <select name="division" class="w-44 rounded-lg border-gray-300 text-sm">
                    <option value="">All divisions</option>
                    @foreach ($divisions as $division)
                        <option value="{{ $division->id }}" @selected(request('division') == $division->id)>
                            {{ $division->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="sr-only">Section</span>
                <select name="section" class="w-44 rounded-lg border-gray-300 text-sm">
                    <option value="">All sections</option>
                    @foreach ($sections as $section)
                        <option value="{{ $section->id }}" @selected(request('section') == $section->id)>
                            {{ $section->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="sr-only">Status</span>
                <select name="status" class="w-32 rounded-lg border-gray-300 text-sm">
                    <option value="">Any status</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                </select>
            </label>
        </x-admin.filter-bar>

        @php
            // Asked of the table, not the page: the Chief may sit on page 3.
            $chief = \App\Models\Employee::query()->where('is_chief_of_hospital', true)->exists();
        @endphp

        @if (! $chief)
            {{-- Not cosmetic: without a Chief of Hospital, IpcrRoutingService
                 cannot resolve a chain for any Section Head or Division Head. --}}
            <div class="rounded-md bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-500/20">
                No Chief of Hospital is set. Until one is, no Section Head or Division Head can submit an IPCR —
                they have nobody to approve it. Edit an employee and set their
                <strong>Approving post</strong> to Chief of Hospital.
            </div>
        @endif

        <x-admin.live-results>
            @include('admin.employees.rows')
        </x-admin.live-results>
        </x-admin.live-list>

        <x-modal name="create-employee" focusable max-width="4xl">
            <form method="POST" action="{{ route('admin.employees.store') }}" class="space-y-4 p-6">
                @csrf
                <h2 class="text-lg font-semibold text-gray-900">New employee</h2>
                <p class="text-sm text-gray-600">
                    Give an email address to create a login at the same time. The temporary password is shown once,
                    on the next screen.
                </p>

                <x-admin.employee-fields :divisions="$divisions" :sections="$sections" :positions="$positions"
                    :designations="$designations" />

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" x-on:click="$dispatch('close-modal', 'create-employee')"
                        class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Cancel</button>
                    <button type="submit"
                        class="rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white hover:bg-nav-800">Create
                        employee</button>
                </div>
            </form>
        </x-modal>
    </x-page-container>
</x-app-layout>

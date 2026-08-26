<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Employees') }}</h2>
            <button type="button" x-data x-on:click="$dispatch('open-modal', 'create-employee')"
                class="inline-flex items-center rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-nav-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-seal focus-visible:ring-offset-2">
                + New Employee
            </button>
        </div>
    </x-slot>

    <x-page-container class="space-y-6">
        <x-admin.flash />

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

        <x-admin.table>
            <x-slot:head>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Employee No.
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Position</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Assignment
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Login</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                <th class="px-6 py-3"></th>
            </x-slot:head>

            @forelse ($employees as $employee)
                <tr>
                    <td class="px-6 py-4 text-sm">
                        <span class="font-medium text-gray-900">{{ $employee->full_name }}</span>
                        {{-- Every approving post, not only the Chief's. Who
                             heads what decides where each IPCR goes, so it
                             belongs on the list rather than one page away. --}}
                        @if ($post = $employee->postTitle())
                            <span
                                class="ms-2 inline-flex items-center rounded-full bg-seal/15 px-2 py-0.5 font-data text-[0.625rem] uppercase tracking-wide text-amber-800 ring-1 ring-inset ring-amber-500/30">{{ $post }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 font-data text-sm text-gray-600">{{ $employee->employee_number }}</td>
                    <td class="px-6 py-4 text-sm text-gray-700">{{ $employee->position?->title ?? '—' }}</td>
                    <td class="px-6 py-4 text-sm text-gray-700">
                        {{ $employee->section?->name ?? $employee->division?->name ?? '—' }}
                        @if ($employee->section)
                            <span class="block text-xs text-gray-400">{{ $employee->section->division?->name }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm">
                        @if ($employee->user)
                            <span class="font-data text-xs text-gray-600">{{ $employee->user->email }}</span>
                        @else
                            <span class="text-xs text-amber-700">No account</span>
                        @endif
                    </td>
                    <td class="px-6 py-4"><x-admin.active-badge :active="$employee->is_active" /></td>
                    <td class="px-6 py-4">
                        <div class="flex items-center justify-end gap-3">
                            <button type="button" x-data
                                x-on:click="$dispatch('open-modal', 'edit-employee-{{ $employee->id }}')"
                                class="text-sm font-medium text-gray-900 hover:underline">Edit</button>

                            <form method="POST" action="{{ route('admin.employees.active', $employee) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="active" value="{{ $employee->is_active ? 0 : 1 }}">
                                <button type="submit" class="text-sm font-medium text-gray-700 hover:underline">
                                    {{ $employee->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </form>

                            {{-- Always offered: this is a soft delete, so IPCR
                                 history survives and the row can be restored. --}}
                            <form method="POST" action="{{ route('admin.employees.destroy', $employee) }}"
                                onsubmit="return confirm('Remove this employee? Their IPCR history is kept.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="text-sm font-medium text-red-600 hover:underline">Remove</button>
                            </form>
                        </div>
                    </td>
                </tr>

                <x-modal name="edit-employee-{{ $employee->id }}" focusable max-width="4xl">
                    <form method="POST" action="{{ route('admin.employees.update', $employee) }}"
                        class="space-y-4 p-6">
                        @csrf
                        @method('PUT')
                        <h2 class="text-lg font-semibold text-gray-900">Edit employee</h2>

                        <x-admin.employee-fields :employee="$employee" :divisions="$divisions" :sections="$sections"
                            :positions="$positions" />

                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button"
                                x-on:click="$dispatch('close-modal', 'edit-employee-{{ $employee->id }}')"
                                class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Cancel</button>
                            <button type="submit"
                                class="rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white hover:bg-nav-800">Save
                                changes</button>
                        </div>
                    </form>
                </x-modal>
            @empty
                <tr>
                    <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">
                        @if (request()->hasAny(['search', 'division', 'section', 'status']))
                            No employees match this search.
                        @else
                            No employees yet. Add one to start assigning heads.
                        @endif
                    </td>
                </tr>
            @endforelse
        </x-admin.table>

        {{ $employees->links() }}

        <x-modal name="create-employee" focusable max-width="4xl">
            <form method="POST" action="{{ route('admin.employees.store') }}" class="space-y-4 p-6">
                @csrf
                <h2 class="text-lg font-semibold text-gray-900">New employee</h2>
                <p class="text-sm text-gray-600">
                    Give an email address to create a login at the same time. The temporary password is shown once,
                    on the next screen.
                </p>

                <x-admin.employee-fields :divisions="$divisions" :sections="$sections" :positions="$positions" />

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

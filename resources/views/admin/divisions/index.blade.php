<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Divisions') }}</h2>
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" x-data x-on:click="$dispatch('open-modal', 'create-section')"
                    @disabled($divisions->isEmpty())
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
        @if (session('status'))
            <div class="rounded-md bg-emerald-50 p-4 text-sm text-emerald-800 ring-1 ring-emerald-500/20">
                {{ session('status') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-md bg-red-50 p-4 text-sm text-red-800 ring-1 ring-red-500/20">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-md bg-red-50 p-4 text-sm text-red-800 ring-1 ring-red-500/20">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        @if ($employees->isEmpty())
            <div class="rounded-md bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-500/20">
                There are no employees yet, so no division head can be assigned. Until a head is set, nobody in
                the division can submit an IPCR.
            </div>
        @endif

        <x-admin.table>
            <x-slot:head>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Division</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Code</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Head</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Sections</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                <th class="px-6 py-3"></th>
            </x-slot:head>

            @forelse ($divisions as $division)
                <tr>
                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $division->name }}</td>
                    <td class="px-6 py-4 font-data text-sm text-gray-600">{{ $division->code ?? '—' }}</td>
                    <td class="px-6 py-4 text-sm">
                        @if ($division->head)
                            <span class="text-gray-900">{{ $division->head->full_name }}</span>
                        @else
                            <span class="text-amber-700">Not assigned</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 font-data text-sm text-gray-600">{{ $division->sections->count() }}</td>
                    <td class="px-6 py-4"><x-admin.active-badge :active="$division->is_active" /></td>
                    <td class="px-6 py-4">
                        <x-admin.row-actions :record="$division" :report="$reports[$division->id]"
                            :active-route="route('admin.divisions.active', $division)"
                            :destroy-route="route('admin.divisions.destroy', $division)">
                            <button type="button" x-data
                                x-on:click="$dispatch('open-modal', 'edit-division-{{ $division->id }}')"
                                class="text-sm font-medium text-gray-900 hover:underline">Edit</button>
                        </x-admin.row-actions>
                    </td>
                </tr>

                {{-- Sections sit inside their division, because that is the only
                     place they can exist: sections.division_id is required. --}}
                @foreach ($division->sections as $section)
                    <tr class="bg-gray-50/60">
                        <td class="py-3 pe-6 ps-12 text-sm text-gray-700">
                            <span class="text-gray-400">└</span> {{ $section->name }}
                        </td>
                        <td class="px-6 py-3 font-data text-sm text-gray-600">{{ $section->code ?? '—' }}</td>
                        <td class="px-6 py-3 text-sm">
                            @if ($section->head)
                                <span class="text-gray-900">{{ $section->head->full_name }}</span>
                            @else
                                <span class="text-amber-700">Not assigned</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-xs uppercase tracking-wide text-gray-400">Section</td>
                        <td class="px-6 py-3"><x-admin.active-badge :active="$section->is_active" /></td>
                        <td class="px-6 py-3">
                            <x-admin.row-actions :record="$section" :report="$sectionReports[$section->id]"
                                :active-route="route('admin.sections.active', $section)"
                                :destroy-route="route('admin.sections.destroy', $section)">
                                <button type="button" x-data
                                    x-on:click="$dispatch('open-modal', 'edit-section-{{ $section->id }}')"
                                    class="text-sm font-medium text-gray-900 hover:underline">Edit</button>
                            </x-admin.row-actions>
                        </td>
                    </tr>

                    <x-modal name="edit-section-{{ $section->id }}" focusable max-width="lg">
                        <form method="POST" action="{{ route('admin.sections.update', $section) }}"
                            class="space-y-4 p-6">
                            @csrf
                            @method('PUT')
                            <h2 class="text-lg font-semibold text-gray-900">Edit section</h2>

                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Division</span>
                                <select name="division_id" required class="w-full rounded-md border-gray-300 text-sm">
                                    @foreach ($divisions as $option)
                                        <option value="{{ $option->id }}"
                                            @selected($section->division_id === $option->id)>{{ $option->name }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Name</span>
                                <input type="text" name="name" value="{{ $section->name }}" required
                                    class="w-full rounded-md border-gray-300 text-sm">
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Code</span>
                                <input type="text" name="code" value="{{ $section->code }}" maxlength="20"
                                    class="w-full rounded-md border-gray-300 text-sm">
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Section Head</span>
                                <select name="section_head_employee_id"
                                    class="w-full rounded-md border-gray-300 text-sm">
                                    <option value="">No head assigned</option>
                                    @foreach ($employees as $employee)
                                        <option value="{{ $employee->id }}"
                                            @selected($section->section_head_employee_id === $employee->id)>
                                            {{ $employee->full_name }}
                                        </option>
                                    @endforeach
                                </select>
                                <span class="mt-1 block text-xs text-gray-500">
                                    This is the assessor for everyone in the section.
                                </span>
                            </label>

                            <div class="flex justify-end gap-3 pt-2">
                                <button type="button"
                                    x-on:click="$dispatch('close-modal', 'edit-section-{{ $section->id }}')"
                                    class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                                    Cancel
                                </button>
                                <button type="submit"
                                    class="rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white hover:bg-nav-800">
                                    Save
                                </button>
                            </div>
                        </form>
                    </x-modal>
                @endforeach

                <x-modal name="edit-division-{{ $division->id }}" focusable max-width="lg">
                    <form method="POST" action="{{ route('admin.divisions.update', $division) }}" class="space-y-4 p-6">
                        @csrf
                        @method('PUT')
                        <h2 class="text-lg font-semibold text-gray-900">Edit division</h2>

                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700">Name</span>
                            <input type="text" name="name" value="{{ $division->name }}" required
                                class="w-full rounded-md border-gray-300 text-sm">
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700">Code</span>
                            <input type="text" name="code" value="{{ $division->code }}" maxlength="20"
                                class="w-full rounded-md border-gray-300 text-sm">
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700">Division Head</span>
                            <select name="division_head_employee_id" class="w-full rounded-md border-gray-300 text-sm">
                                <option value="">No head assigned</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}"
                                        @selected($division->division_head_employee_id === $employee->id)>
                                        {{ $employee->full_name }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="mt-1 block text-xs text-gray-500">
                                Required before anyone in this division can submit an IPCR.
                            </span>
                        </label>

                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button"
                                x-on:click="$dispatch('close-modal', 'edit-division-{{ $division->id }}')"
                                class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit"
                                class="rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white hover:bg-nav-800">
                                Save
                            </button>
                        </div>
                    </form>
                </x-modal>
            @empty
                <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">No divisions yet.</td>
                </tr>
            @endforelse
        </x-admin.table>

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
                        @foreach ($divisions as $division)
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

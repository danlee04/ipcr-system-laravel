<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Job Titles') }}</h2>
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
        <div class="flex items-center justify-between gap-4 border-b border-gray-200">
            <nav class="-mb-px flex gap-6" aria-label="Job title type">
                <a href="{{ route('admin.job-titles.index') }}"
                    class="border-b-2 px-1 pb-3 text-sm font-medium {{ $tab === 'positions' ? 'border-nav-900 text-nav-900' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    Positions ({{ $positions->count() }})
                </a>
                <a href="{{ route('admin.job-titles.index', ['tab' => 'designations']) }}"
                    class="border-b-2 px-1 pb-3 text-sm font-medium {{ $tab === 'designations' ? 'border-nav-900 text-nav-900' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    Designations ({{ $designations->count() }})
                </a>
            </nav>

            <button type="button" x-data
                x-on:click="$dispatch('open-modal', '{{ $tab === 'positions' ? 'create-position' : 'create-designation' }}')"
                class="mb-2 inline-flex items-center rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-nav-800">
                + New {{ $tab === 'positions' ? 'Position' : 'Designation' }}
            </button>
        </div>

        @if ($tab === 'positions')
            <x-admin.table>
                <x-slot:head>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Title</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Item No.
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">SG</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status
                    </th>
                    <th class="px-6 py-3"></th>
                </x-slot:head>

                @forelse ($positions as $position)
                    <tr>
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $position->title }}</td>
                        <td class="px-6 py-4 font-data text-sm text-gray-600">{{ $position->item_number ?? '—' }}</td>
                        <td class="px-6 py-4 font-data text-sm text-gray-600">{{ $position->salary_grade ?? '—' }}</td>
                        <td class="px-6 py-4"><x-admin.active-badge :active="$position->is_active" /></td>
                        <td class="px-6 py-4">
                            <x-admin.row-actions :record="$position" :report="$positionReports[$position->id]"
                                :active-route="route('admin.positions.active', $position)"
                                :destroy-route="route('admin.positions.destroy', $position)">
                                <button type="button" x-data
                                    x-on:click="$dispatch('open-modal', 'edit-position-{{ $position->id }}')"
                                    class="text-sm font-medium text-gray-900 hover:underline">Edit</button>
                            </x-admin.row-actions>
                        </td>
                    </tr>

                    <x-modal name="edit-position-{{ $position->id }}" focusable max-width="lg">
                        <form method="POST" action="{{ route('admin.positions.update', $position) }}"
                            class="space-y-4 p-6">
                            @csrf
                            @method('PUT')
                            <h2 class="text-lg font-semibold text-gray-900">Edit position</h2>

                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Title</span>
                                <input type="text" name="title" value="{{ $position->title }}" required
                                    class="w-full rounded-md border-gray-300 text-sm">
                            </label>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700">Item number</span>
                                    <input type="text" name="item_number" value="{{ $position->item_number }}"
                                        maxlength="50" class="w-full rounded-md border-gray-300 text-sm">
                                </label>

                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700">Salary grade</span>
                                    <input type="number" name="salary_grade" value="{{ $position->salary_grade }}"
                                        min="1" max="33" class="w-full rounded-md border-gray-300 text-sm">
                                </label>
                            </div>

                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Description</span>
                                <textarea name="description" rows="3" class="w-full rounded-md border-gray-300 text-sm">{{ $position->description }}</textarea>
                            </label>

                            <div class="flex justify-end gap-3 pt-2">
                                <button type="button"
                                    x-on:click="$dispatch('close-modal', 'edit-position-{{ $position->id }}')"
                                    class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Cancel</button>
                                <button type="submit"
                                    class="rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white hover:bg-nav-800">Save
                                    changes</button>
                            </div>
                        </form>
                    </x-modal>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">No positions yet.</td>
                    </tr>
                @endforelse
            </x-admin.table>

            <x-modal name="create-position" focusable max-width="lg">
                <form method="POST" action="{{ route('admin.positions.store') }}" class="space-y-4 p-6">
                    @csrf
                    <h2 class="text-lg font-semibold text-gray-900">New position</h2>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">Title</span>
                        <input type="text" name="title" required placeholder="Statistician II"
                            class="w-full rounded-md border-gray-300 text-sm">
                    </label>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700">Item number</span>
                            <input type="text" name="item_number" maxlength="50" placeholder="STAT-002"
                                class="w-full rounded-md border-gray-300 text-sm">
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700">Salary grade</span>
                            <input type="number" name="salary_grade" min="1" max="33"
                                class="w-full rounded-md border-gray-300 text-sm">
                        </label>
                    </div>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">Description</span>
                        <textarea name="description" rows="3" class="w-full rounded-md border-gray-300 text-sm"></textarea>
                    </label>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" x-on:click="$dispatch('close-modal', 'create-position')"
                            class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Cancel</button>
                        <button type="submit"
                            class="rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white hover:bg-nav-800">Create
                            position</button>
                    </div>
                </form>
            </x-modal>
        @else
            <x-admin.table>
                <x-slot:head>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Title</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status
                    </th>
                    <th class="px-6 py-3"></th>
                </x-slot:head>

                @forelse ($designations as $designation)
                    <tr>
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $designation->title }}</td>
                        <td class="px-6 py-4"><x-admin.active-badge :active="$designation->is_active" /></td>
                        <td class="px-6 py-4">
                            <x-admin.row-actions :record="$designation" :report="$designationReports[$designation->id]"
                                :active-route="route('admin.designations.active', $designation)"
                                :destroy-route="route('admin.designations.destroy', $designation)">
                                <button type="button" x-data
                                    x-on:click="$dispatch('open-modal', 'edit-designation-{{ $designation->id }}')"
                                    class="text-sm font-medium text-gray-900 hover:underline">Edit</button>
                            </x-admin.row-actions>
                        </td>
                    </tr>

                    <x-modal name="edit-designation-{{ $designation->id }}" focusable max-width="lg">
                        <form method="POST" action="{{ route('admin.designations.update', $designation) }}"
                            class="space-y-4 p-6">
                            @csrf
                            @method('PUT')
                            <h2 class="text-lg font-semibold text-gray-900">Edit designation</h2>

                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Title</span>
                                <input type="text" name="title" value="{{ $designation->title }}" required
                                    class="w-full rounded-md border-gray-300 text-sm">
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Description</span>
                                <textarea name="description" rows="3" class="w-full rounded-md border-gray-300 text-sm">{{ $designation->description }}</textarea>
                            </label>

                            <div class="flex justify-end gap-3 pt-2">
                                <button type="button"
                                    x-on:click="$dispatch('close-modal', 'edit-designation-{{ $designation->id }}')"
                                    class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Cancel</button>
                                <button type="submit"
                                    class="rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white hover:bg-nav-800">Save
                                    changes</button>
                            </div>
                        </form>
                    </x-modal>
                @empty
                    <tr>
                        <td colspan="3" class="px-6 py-8 text-center text-sm text-gray-500">No designations yet.</td>
                    </tr>
                @endforelse
            </x-admin.table>

            <x-modal name="create-designation" focusable max-width="lg">
                <form method="POST" action="{{ route('admin.designations.store') }}" class="space-y-4 p-6">
                    @csrf
                    <h2 class="text-lg font-semibold text-gray-900">New designation</h2>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">Title</span>
                        <input type="text" name="title" required placeholder="OIC - Budget Officer"
                            class="w-full rounded-md border-gray-300 text-sm">
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">Description</span>
                        <textarea name="description" rows="3" class="w-full rounded-md border-gray-300 text-sm"></textarea>
                    </label>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" x-on:click="$dispatch('close-modal', 'create-designation')"
                            class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Cancel</button>
                        <button type="submit"
                            class="rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white hover:bg-nav-800">Create
                            designation</button>
                    </div>
                </form>
            </x-modal>
        @endif
    </x-page-container>
</x-app-layout>

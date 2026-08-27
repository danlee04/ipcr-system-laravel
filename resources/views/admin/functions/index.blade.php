<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Functions') }}</h2>
            <button type="button" x-data x-on:click="$dispatch('open-modal', 'create-function')"
                class="inline-flex items-center rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-nav-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-seal focus-visible:ring-offset-2">
                + New Function
            </button>
        </div>
    </x-slot>

    <x-page-container class="space-y-6">
        <x-admin.flash />

        <p class="max-w-3xl text-sm text-gray-600">
            The catalog employees pick from when building an IPCR. Nothing here is added automatically — these are
            suggestions. A <strong>core</strong> function reaches whoever holds its position;
            <strong>strategic</strong> and <strong>support</strong> reach whoever holds its designation;
            <strong>common</strong> reaches everyone.
        </p>

        {{-- Division narrows Section, Section narrows Position: the three
             selects can never describe a combination that has no rows. --}}
        <x-admin.filter-bar :action="route('admin.functions.index')"
            placeholder="Search by output or success indicator">
            <label class="block">
                <span class="sr-only">Category</span>
                <select name="category" class="w-40 rounded-lg border-gray-300 text-sm">
                    <option value="">All categories</option>
                    @foreach (\App\Enums\FunctionCategory::cases() as $option)
                        <option value="{{ $option->value }}" @selected(request('category') === $option->value)>
                            {{ $option->label() }}</option>
                    @endforeach
                </select>
            </label>

            <div class="flex flex-wrap items-end gap-2"
                x-data="{ division: '{{ request('division') }}', section: '{{ request('section') }}' }">
                <label class="block">
                    <span class="sr-only">Division</span>
                    <select name="division" x-model="division" x-on:change="section = ''"
                        class="w-40 rounded-lg border-gray-300 text-sm">
                        <option value="">All divisions</option>
                        @foreach ($divisions as $division)
                            <option value="{{ $division->id }}">{{ $division->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="sr-only">Section</span>
                    <select name="section" x-model="section" class="w-44 rounded-lg border-gray-300 text-sm">
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
                    <select name="position" class="w-44 rounded-lg border-gray-300 text-sm">
                        <option value="">All positions</option>
                        @foreach ($positions as $position)
                            <option value="{{ $position->id }}" data-section="{{ $position->section_id }}"
                                data-division="{{ $position->section?->division_id }}"
                                x-show="(section === '' || section === '{{ $position->section_id }}')
                                    && (division === '' || division === '{{ $position->section?->division_id }}')"
                                @selected(request('position') == $position->id)>
                                {{ $position->title }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </x-admin.filter-bar>

        <p class="-mt-3 text-xs text-gray-500">
            The filters narrow position functions. Common functions belong to everyone, so they are always listed.
        </p>

        @if ($unfiledCount > 0)
            {{-- These fail only at the moment an employee tries to use them,
                 which is far too late to be useful feedback. --}}
            <div class="rounded-md bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-500/20">
                <strong>{{ $unfiledCount }} common
                    function{{ $unfiledCount === 1 ? '' : 's' }} cannot be added to an IPCR yet.</strong>
                Common is a pool, not a rating category — each one needs to say whether it counts towards Strategic,
                Core or Support. Edit it and set <em>Counts towards</em>.
            </div>
        @endif

        <x-admin.table>
            <x-slot:head>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Category
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Output</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Applies to
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                <th class="px-6 py-3"></th>
            </x-slot:head>

            @forelse ($functions as $function)
                <tr>
                    <td class="px-6 py-4">
                        <span
                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $function->category->badgeClasses() }}">
                            {{ $function->category->label() }}
                        </span>
                        @if ($function->category === \App\Enums\FunctionCategory::Common)
                            @if ($function->rating_category)
                                <span class="mt-1 block text-xs text-gray-500">
                                    counts as {{ $function->rating_category->label() }}
                                </span>
                            @else
                                <span class="mt-1 block text-xs font-medium text-amber-700">no rating category</span>
                            @endif
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm">
                        <span class="block max-w-md text-gray-900">{{ $function->title }}</span>
                        @if ($function->success_indicator)
                            <span
                                class="mt-1 block max-w-md text-xs text-gray-500">{{ $function->success_indicator }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-700">
                        {{ $function->position?->title ?? $function->designation?->title ?? 'Everyone' }}
                    </td>
                    <td class="px-6 py-4"><x-admin.active-badge :active="$function->is_active" /></td>
                    <td class="px-6 py-4">
                        <div class="flex items-center justify-end gap-3">
                            <button type="button" x-data
                                x-on:click="$dispatch('open-modal', 'edit-function-{{ $function->id }}')"
                                class="text-sm font-medium text-gray-900 hover:underline">Edit</button>

                            <form method="POST" action="{{ route('admin.functions.active', $function) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="active" value="{{ $function->is_active ? 0 : 1 }}">
                                <button type="submit" class="text-sm font-medium text-gray-700 hover:underline">
                                    {{ $function->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </form>

                            <form method="POST" action="{{ route('admin.functions.destroy', $function) }}"
                                onsubmit="return confirm('Delete this function from the catalog? IPCRs already using it keep their own copy.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="text-sm font-medium text-red-600 hover:underline">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>

                <x-modal name="edit-function-{{ $function->id }}" focusable max-width="2xl">
                    <form method="POST" action="{{ route('admin.functions.update', $function) }}"
                        class="space-y-4 p-6">
                        @csrf
                        @method('PUT')
                        <h2 class="text-lg font-semibold text-gray-900">Edit function</h2>
                        <x-admin.function-fields :function="$function" :positions="$positions"
                            :designations="$designations" :divisions="$divisions" :sections="$sections" />

                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button"
                                x-on:click="$dispatch('close-modal', 'edit-function-{{ $function->id }}')"
                                class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Cancel</button>
                            <button type="submit"
                                class="rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white hover:bg-nav-800">Save
                                changes</button>
                        </div>
                    </form>
                </x-modal>
            @empty
                <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">
                        @if (request()->hasAny(['search', 'category']))
                            No functions match this search.
                        @else
                            No functions in the catalog yet. Employees can still type their own on the IPCR.
                        @endif
                    </td>
                </tr>
            @endforelse
        </x-admin.table>

        {{ $functions->links() }}

        <x-modal name="create-function" focusable max-width="2xl">
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

{{-- Only while a filter is on. It answers a question nobody has until
     they narrow the list and still see functions from elsewhere. --}}
@if (request()->hasAny(['division', 'section', 'position']))
    <p class="-mt-3 text-xs text-gray-500">
        Common functions and those on a designation sit in no division, so they are always listed.
    </p>
@endif

<x-admin.table>
    <x-slot:head>
        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Output / MFO</th>
        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Success Indicator
        </th>
        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Category</th>
        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Rated On</th>
        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
        <th class="px-6 py-3"></th>
    </x-slot:head>

    {{-- Four blocks, each holding its own five rows and its own page number.

         They are counted separately on purpose: how much core work the
         hospital has written down says nothing about how many lines everybody
         carries, and one shared page number would have paged them together
         and buried whichever block came second. --}}
    @foreach ($groups as $label => $page)
        @continue($page->isEmpty())

        {{-- The block named once, over its own rows. A th with
             scope="colgroup" is what heads a group of rows - a screen reader
             announces it before each one. --}}
        <tr class="bg-gray-50">
            <th scope="colgroup" colspan="6"
                class="px-6 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">
                {{ $label }}
                <span class="ms-2 font-data text-[0.625rem] font-normal normal-case tracking-normal text-gray-400">
                    {{ $page->total() }}
                </span>
            </th>
        </tr>

        @foreach ($page as $function)
        <tr>
            {{-- Clamped, not truncated in PHP: the full text is still there
                 for anyone who needs it, and an output two paragraphs long no
                 longer sets the height of the row. --}}
            <td class="px-6 py-4 text-sm">
                <span class="line-clamp-2 block max-w-sm text-gray-900">{{ $function->title }}</span>
                {{-- Who it reaches. Kept as a subtitle rather than a column of
                     its own: the list is filtered by division, section and
                     position, and a filtered list that will not say what each
                     row is tied to is hard to read. --}}
                <span class="mt-0.5 block text-xs text-gray-500">
                    {{ $function->position?->title ?? $function->designation?->title ?? 'Common function' }}
                </span>
            </td>
            {{-- One line, and given the room to be worth reading. A success
                 indicator is a sentence with numbers in it; two clamped lines
                 of it told you less than one wide one, and set the height of
                 every row while doing it. The whole text is on hover. --}}
            <td class="px-6 py-4 text-sm">
                <span class="line-clamp-1 block max-w-xl text-gray-600"
                    title="{{ $function->success_indicator }}">{{ $function->success_indicator ?: '—' }}</span>
            </td>
            <td class="px-6 py-4">
                <span
                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $function->category->badgeClasses() }}">
                    {{ $function->category->label() }}
                </span>
            </td>
            {{-- Which measures the rubric grades, and nothing about how. The
                 levels and their ranges belong in the editor, where they can
                 be read - three five-level scales flattened into a table cell
                 are unreadable in every row at once. --}}
            <td class="px-6 py-4">
                @if ($function->measures->isEmpty())
                    <span class="text-xs text-gray-400">By hand</span>
                @else
                    <div class="flex items-center gap-1">
                        @foreach (\App\Enums\RatingMeasure::cases() as $case)
                            @php $rated = $function->measures->firstWhere('measure', $case); @endphp
                            @if ($rated)
                                <span
                                    class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-nav-900/5 font-data text-xs font-semibold text-nav-900 ring-1 ring-inset ring-nav-900/10"
                                    title="{{ $case->label() }} — {{ strtolower($rated->answer->label()) }}{{ $rated->unit ? ' in ' . $rated->unit : '' }}">
                                    {{ strtoupper($case->key()) }}
                                </span>
                            @endif
                        @endforeach
                    </div>
                @endif
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
        @endforeach

        @if ($page->hasPages())
            <tr>
                <td colspan="6" class="border-t border-gray-100 bg-gray-50/60 px-6 py-2">
                    {{ $page->links() }}
                </td>
            </tr>
        @endif
    @endforeach

    @if ($functions->isEmpty())
        <tr>
            <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">
                @if (request()->hasAny(['search', 'category']))
                    No functions match this search.
                @else
                    No functions in the catalog yet. Employees can still type their own on the IPCR.
                @endif
            </td>
        </tr>
    @endif
</x-admin.table>

{{-- The editors, kept outside the table. A modal is a <div>, and a <div> inside <tbody> is not
     valid HTML: the parser throws it out of the table, and what was
     meant to stay hidden until asked for leaks onto the page. --}}
@foreach ($functions as $function)
    <x-modal name="edit-function-{{ $function->id }}" focusable max-width="4xl">
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
@endforeach


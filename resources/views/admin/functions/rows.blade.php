{{-- Only while a filter is on. It answers a question nobody has until
     they narrow the list and still see functions from elsewhere. --}}
@if (request()->hasAny(['division', 'section', 'position']))
    <p class="-mt-3 text-xs text-gray-500">
        Functions tied to no position — common, and those on a designation — are always listed.
    </p>
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
            </td>
            <td class="px-6 py-4 text-sm">
                {{-- Clamped, not truncated in PHP: the full text is
                     still there for anyone who needs it, and a success
                     indicator two paragraphs long no longer sets the
                     height of the row. --}}
                <span class="line-clamp-2 block max-w-md text-gray-900">{{ $function->title }}</span>
                @if ($function->success_indicator)
                    <span class="mt-0.5 line-clamp-1 block max-w-md text-xs text-gray-500"
                        title="{{ $function->success_indicator }}">{{ $function->success_indicator }}</span>
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

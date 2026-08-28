@props(['function', 'withCategory' => false])

{{-- One function to tick. `picked` belongs to the form around it, which is
     what counts the choice and labels the button.

     Anything already on the IPCR never reaches this component - it is filtered
     out of the list instead, because a choice already made is not a choice. --}}
<label
    class="flex cursor-pointer items-start gap-3 rounded-md border border-gray-200 px-3 py-2 hover:border-nav-900/30 hover:bg-gray-50">
    <input type="checkbox" name="job_function_ids[]" value="{{ $function->id }}"
        x-on:change="picked += $event.target.checked ? 1 : -1"
        class="mt-0.5 rounded border-gray-300 text-nav-900 focus:ring-seal">

    <span class="min-w-0 flex-1">
        <span class="block text-sm text-gray-800">{{ $function->title }}</span>

        @if ($withCategory)
            <span
                class="mt-1 inline-flex items-center rounded-full px-2 py-0.5 text-[0.625rem] font-medium uppercase tracking-wide ring-1 ring-inset {{ $function->category->badgeClasses() }}">
                {{ $function->category->label() }}
            </span>
        @endif

        @if ($function->success_indicator)
            <span class="mt-0.5 block text-xs text-gray-500">{{ $function->success_indicator }}</span>
        @endif
    </span>
</label>

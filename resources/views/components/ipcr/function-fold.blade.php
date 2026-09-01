@props(['label', 'items'])

{{-- One category, shut until it is asked for.

     A hospital's catalog runs to dozens of lines per post. Printed out in
     full above the sheet they buried it, so what shows is the heading and
     the count - enough to decide whether to look inside. --}}
<details class="rounded-lg ring-1 ring-inset ring-gray-200">
    <summary
        class="flex cursor-pointer items-center justify-between gap-3 rounded-lg px-4 py-2.5 text-sm hover:bg-gray-50">
        <span class="font-medium text-gray-800">{{ $label }}</span>
        <span class="font-data text-xs text-gray-400">{{ $items->count() }}</span>
    </summary>

    <div class="space-y-1.5 border-t border-gray-200 p-3">
        @foreach ($items as $jobFunction)
            <x-ipcr.catalog-choice :function="$jobFunction" />
        @endforeach
    </div>
</details>

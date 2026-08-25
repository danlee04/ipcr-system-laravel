@props(['record', 'report', 'activeRoute', 'destroyRoute'])

{{-- Deactivate / activate is the normal retirement path; delete is the
     exception and is only offered when nothing references the record. --}}
<div class="flex items-center justify-end gap-3">
    {{ $slot }}

    <form method="POST" action="{{ $activeRoute }}">
        @csrf
        @method('PATCH')
        <input type="hidden" name="active" value="{{ $record->is_active ? 0 : 1 }}">
        <button type="submit" class="text-sm font-medium text-gray-700 hover:underline">
            {{ $record->is_active ? 'Deactivate' : 'Activate' }}
        </button>
    </form>

    @if ($report->deletable)
        <form method="POST" action="{{ $destroyRoute }}"
            onsubmit="return confirm('Delete this permanently? This cannot be undone.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-sm font-medium text-red-600 hover:underline">Delete</button>
        </form>
    @else
        <span class="cursor-not-allowed text-sm font-medium text-gray-300" title="{{ $report->message() }}">Delete</span>
    @endif
</div>

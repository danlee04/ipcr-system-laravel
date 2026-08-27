@props(['ipcr'])

{{-- Nothing at all when it was on time. A green "On time" beside every name
     would drown the handful that need looking at. --}}
@if ($ipcr?->isLate())
    <span
        {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-500/30']) }}
        title="Deadline was {{ $ipcr->period->submission_deadline->format('d M Y') }}">
        {{ $ipcr->daysLate() }} {{ Str::plural('day', $ipcr->daysLate()) }} late
    </span>
@endif

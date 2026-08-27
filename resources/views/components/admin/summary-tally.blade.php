@props(['tally', 'size' => 'sm'])

@php
    $chip = 'inline-flex items-center rounded-full ring-1 ring-inset '
        . ($size === 'lg' ? 'px-3 py-1.5 text-sm' : 'px-2.5 py-1 text-xs');
@endphp

{{-- The number and its word stay in one breath - "12 submitted", not a figure
     stacked over a caption. This is read as a sentence about an office, not
     watched as a dashboard. --}}
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2']) }}>
    <span class="{{ $chip }} bg-gray-100 font-medium text-gray-700 ring-gray-500/20">
        {{ $tally->expected }} {{ Str::plural('employee', $tally->expected) }}
    </span>

    <span class="{{ $chip }} bg-sky-50 font-medium text-sky-800 ring-sky-500/20">
        {{ $tally->submitted }} submitted
    </span>

    <span class="{{ $chip }} bg-emerald-50 font-medium text-emerald-800 ring-emerald-500/20">
        {{ $tally->approved }} approved
    </span>

    @if ($tally->outstanding() > 0)
        <span class="{{ $chip }} bg-amber-50 font-medium text-amber-800 ring-amber-500/20">
            {{ $tally->outstanding() }} outstanding
        </span>
    @endif

    {{-- Absent rather than nought while nobody is approved. A section showing
         0.00 reads as a failing one. --}}
    @if ($tally->average !== null)
        <span class="{{ $chip }} bg-nav-900/5 font-data font-medium text-nav-900 ring-nav-900/10">
            Average {{ number_format($tally->average, 2) }}
        </span>
    @endif
</div>

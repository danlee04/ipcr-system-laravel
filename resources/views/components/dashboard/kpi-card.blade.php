@props(['label', 'value', 'sub', 'percent' => 0, 'accent' => 'blue', 'icon' => null, 'denominator' => null])

@php
    // Each accent is spelled out rather than interpolated: Tailwind scans this
    // file for literal class names and would not see a built-up string.
    $accents = [
        'blue' => ['rail' => 'bg-sky-500', 'chip' => 'bg-sky-50 text-sky-700', 'bar' => 'bg-sky-500'],
        'amber' => ['rail' => 'bg-amber-500', 'chip' => 'bg-amber-50 text-amber-700', 'bar' => 'bg-amber-500'],
        'teal' => ['rail' => 'bg-teal-500', 'chip' => 'bg-teal-50 text-teal-700', 'bar' => 'bg-teal-500'],
        'red' => ['rail' => 'bg-red-500', 'chip' => 'bg-red-50 text-red-700', 'bar' => 'bg-red-500'],
        'green' => ['rail' => 'bg-emerald-600', 'chip' => 'bg-emerald-50 text-emerald-700', 'bar' => 'bg-emerald-600'],
    ];
    $a = $accents[$accent] ?? $accents['blue'];
@endphp

<div
    {{ $attributes->class('relative overflow-hidden rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 transition-shadow hover:shadow-md') }}>
    <span aria-hidden="true" class="absolute inset-y-0 start-0 w-[3px] {{ $a['rail'] }}"></span>

    <div class="flex items-center justify-between">
        <span class="grid h-8 w-8 place-items-center rounded-lg {{ $a['chip'] }}">
            {{ $icon }}
        </span>
        <span class="rounded-md px-1.5 py-0.5 font-data text-[0.6875rem] font-semibold {{ $a['chip'] }}">
            {{ $percent }}%
        </span>
    </div>

    <p class="mt-2.5 text-[0.6875rem] font-medium uppercase tracking-wide text-gray-500">{{ $label }}</p>

    {{-- The denominator, where there is one, is what the figure means: eleven
         sheets assessed says nothing until you know how many people the unit
         has. It is set smaller so the two are not read as one number. --}}
    <p class="mt-0.5 flex items-baseline gap-1 font-data leading-none">
        <span data-kpi-value class="text-3xl font-semibold text-gray-900">{{ $value }}</span>
        @if ($denominator !== null)
            <span class="text-sm font-medium text-gray-400">/ {{ $denominator }}</span>
        @endif
    </p>

    <p class="mt-1 text-[0.6875rem] text-gray-400">{{ $sub }}</p>

    <div class="absolute inset-x-0 bottom-0 h-0.5 bg-gray-950/5">
        <div class="h-full {{ $a['bar'] }}" style="width: {{ min(100, max(0, $percent)) }}%"></div>
    </div>
</div>

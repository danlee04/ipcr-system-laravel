@props(['ipcr', 'catalog'])

@php
    // What is on the IPCR is off the list. A picker is for choosing, and a
    // choice already made is not a choice - it is a row in the table below.
    $taken = $ipcr->items->pluck('job_function_id')->filter()->all();
    $left = fn($items) => $items->reject(fn($function) => in_array($function->id, $taken))->values();

    // Two sources, and they read differently. What reaches this employee
    // through their own post or designation is theirs alone; what reaches
    // everyone is the hospital's, and is the same short list on every IPCR.
    // Mixing the two made a long single column where nothing stood out.
    $mine = collect([
        'Core Function' => $catalog->core,
        'Support Function' => $catalog->support,
        'Strategic Function' => $catalog->strategic,
    ])
        ->map(fn($items) => $left($items->reject->reachesEveryone()))
        ->filter(fn($items) => $items->isNotEmpty());

    $everyone = $left($catalog->all()->filter->reachesEveryone());
@endphp

<div class="space-y-6 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-950/5">
    <div>
        <h3 class="text-sm font-semibold text-gray-900">Add a Function</h3>
        <p class="mt-0.5 text-sm text-gray-600">
            Tick everything that belongs on your IPCR and add it in one go. Nothing is added for you.
        </p>
    </div>

    @if ($mine->isNotEmpty() || $everyone->isNotEmpty())
        <form method="POST" action="{{ route('ipcrs.items.catalog', $ipcr) }}" class="space-y-5"
            x-data="{ picked: 0 }">
            @csrf

            <div class="grid gap-6 lg:grid-cols-3">
                {{-- Theirs, folded by category. --}}
                <div class="space-y-3 lg:col-span-2">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
                        From your position and designations
                    </p>

                    @forelse ($mine as $label => $items)
                        <details class="rounded-lg ring-1 ring-inset ring-gray-200" open>
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
                    @empty
                        <p class="rounded-lg bg-gray-50 p-4 text-sm text-gray-500 ring-1 ring-inset ring-gray-200">
                            Nothing left to add from your position or designations. Anything missing has to be added to
                            the catalog by HR.
                        </p>
                    @endforelse
                </div>

                {{-- The hospital's, flat: a handful of lines that everybody
                     carries, so folding them would hide more than it saves. --}}
                <div class="space-y-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Open to everyone</p>

                    @forelse ($everyone as $jobFunction)
                        <x-ipcr.catalog-choice :function="$jobFunction" with-category />
                    @empty
                        <p class="rounded-lg bg-gray-50 p-4 text-sm text-gray-500 ring-1 ring-inset ring-gray-200">
                            Nothing left that is open to everyone.
                        </p>
                    @endforelse
                </div>
            </div>

            <button type="submit" x-bind:disabled="picked === 0"
                class="rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-nav-800 disabled:cursor-not-allowed disabled:bg-gray-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-seal focus-visible:ring-offset-2">
                <span x-show="picked === 0">Select the functions to add</span>
                <span x-show="picked > 0" x-cloak>
                    Add <span x-text="picked"></span>
                    <span x-text="picked === 1 ? 'function' : 'functions'"></span>
                </span>
            </button>
        </form>
    @else
        <p class="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-900 ring-1 ring-inset ring-emerald-500/20">
            Everything the catalog offers you is already on this IPCR.
        </p>
    @endif
</div>

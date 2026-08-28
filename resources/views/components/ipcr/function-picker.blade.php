@props(['ipcr', 'catalog'])

@php
    // What is on the IPCR is off the list. A picker is for choosing, and a
    // choice already made is not a choice - it is a row in the table below.
    $taken = $ipcr->items->pluck('job_function_id')->filter()->all();
    $left = fn($items) => $items->reject(fn($function) => in_array($function->id, $taken))->values();

    // Three sources, and they read differently. What reaches this employee
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

    // The third: somebody else's post. Grouped by the post it came from,
    // because which one it was is the whole point of borrowing it.
    $others = $left($catalog->elsewhere)->groupBy(fn($function) => $function->position?->title ?? 'Unassigned');
@endphp

<div class="space-y-6 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-950/5">
    <div>
        <h3 class="text-sm font-semibold text-gray-900">Add a Function</h3>
        <p class="mt-0.5 text-sm text-gray-600">
            Tick everything that belongs on your IPCR and add it in one go. Nothing is added for you.
        </p>
    </div>

    @if ($mine->isNotEmpty() || $everyone->isNotEmpty() || $others->isNotEmpty())
        <form method="POST" action="{{ route('ipcrs.items.catalog', $ipcr) }}" class="space-y-5"
            x-data="{ picked: 0 }">
            @csrf

            {{-- The two everyday sources, half the width each. --}}
            <div class="grid gap-6 lg:grid-cols-2">
                {{-- Theirs, folded by category. --}}
                <div class="space-y-3">
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

            {{-- Somebody else's post. Shut until it is opened, and below the
                 two columns rather than beside them: this is the exception -
                 covering a vacancy, or work that moved before the catalog
                 caught up - and it should take a decision to reach it. --}}
            @if ($others->isNotEmpty())
                <details class="rounded-lg ring-1 ring-inset ring-gray-200">
                    <summary
                        class="flex cursor-pointer items-center justify-between gap-3 rounded-lg px-4 py-2.5 text-sm hover:bg-gray-50">
                        <span class="font-medium text-gray-800">From another position</span>
                        <span class="font-data text-xs text-gray-400">{{ $others->flatten()->count() }}</span>
                    </summary>

                    <div class="space-y-4 border-t border-gray-200 p-4">
                        <p class="text-xs text-gray-500">
                            Work the catalog files under a post that is not yours - for covering a vacancy, or a task
                            that moved before the catalog caught up. Add one only if you actually did it.
                        </p>

                        @foreach ($others as $position => $items)
                            <div class="space-y-1.5">
                                <p class="font-data text-[0.6875rem] uppercase tracking-wide text-gray-500">
                                    {{ $position }}
                                </p>

                                <div class="grid gap-1.5 sm:grid-cols-2">
                                    @foreach ($items as $jobFunction)
                                        <x-ipcr.catalog-choice :function="$jobFunction" with-category />
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </details>
            @endif

            <button type="submit" x-bind:disabled="picked === 0"
                class="rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-nav-800 disabled:cursor-not-allowed disabled:bg-gray-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2">
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

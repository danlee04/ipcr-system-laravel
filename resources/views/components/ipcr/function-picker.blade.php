@props(['ipcr', 'catalog'])

@php
    // What is on the IPCR is off the list. A picker is for choosing, and a
    // choice already made is not a choice - it is a row in the table below.
    $taken = $ipcr->items->pluck('job_function_id')->filter()->all();
    $left = fn($items) => $items->reject(fn($function) => in_array($function->id, $taken))->values();

    // Core, then support, then strategic - the order the sheet is read in,
    // wherever it appears.
    $byCategory = collect([
        'Core Function' => $catalog->core,
        'Support Function' => $catalog->support,
        'Strategic Function' => $catalog->strategic,
    ]);

    // Two sources, and they read differently. What reaches this employee
    // through their own post or designation is theirs alone; what reaches
    // everyone is the hospital's, and is the same list on every IPCR in the
    // building. Both fold by the same three categories, because the common
    // lines are not a fourth kind of work - they are the same three, open to
    // everybody.
    $mine = $byCategory
        ->map(fn($items) => $left($items->reject->reachesEveryone()))
        ->filter->isNotEmpty();

    $everyone = $byCategory
        ->map(fn($items) => $left($items->filter->reachesEveryone()))
        ->filter->isNotEmpty();

    // The third: somebody else's post.
    $borrowable = $left($catalog->elsewhere);

    // Grouped by the post rather than its title, because that id is what the
    // filter below matches on.
    $others = $borrowable->groupBy(fn($function) => (string) $function->position_id);

    // Only what has something behind it. A hospital has hundreds of posts and
    // most of them are nothing to do with this employee; offering a division
    // that narrows to an empty list is a dead end dressed as a choice.
    $otherPositions = $borrowable->map->position->filter()->unique('id')->sortBy('title')->values();
    $otherSections = $otherPositions->map->section->filter()->unique('id')->sortBy('name')->values();
    $otherDivisions = $otherSections->map->division->filter()->unique('id')->sortBy('name')->values();
@endphp

<div class="space-y-5 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-950/5">
    <div>
        <h3 class="text-sm font-semibold text-gray-900">Add a Function</h3>
        <p class="mt-0.5 text-sm text-gray-600">
            Open a category, tick what belongs on your IPCR, and add them in one go. Nothing is added for you.
        </p>
    </div>

    @if ($mine->isNotEmpty() || $everyone->isNotEmpty() || $others->isNotEmpty())
        <form method="POST" action="{{ route('ipcrs.items.catalog', $ipcr) }}" class="space-y-5"
            x-data="{ picked: 0 }">
            @csrf

            {{-- The two everyday sources, half the width each. --}}
            <div class="grid gap-5 lg:grid-cols-2">
                <div class="space-y-2">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
                        From your position and designations
                    </p>

                    @forelse ($mine as $label => $items)
                        <x-ipcr.function-fold :label="$label" :items="$items" />
                    @empty
                        <p class="rounded-lg bg-gray-50 p-4 text-sm text-gray-500 ring-1 ring-inset ring-gray-200">
                            Nothing left to add from your position or designations. Anything missing has to be added to
                            the catalog by HR.
                        </p>
                    @endforelse
                </div>

                <div class="space-y-2">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Open to everyone</p>

                    @forelse ($everyone as $label => $items)
                        <x-ipcr.function-fold :label="$label" :items="$items" />
                    @empty
                        <p class="rounded-lg bg-gray-50 p-4 text-sm text-gray-500 ring-1 ring-inset ring-gray-200">
                            Nothing left that is open to everyone.
                        </p>
                    @endforelse
                </div>
            </div>

            {{-- Somebody else's post. Below the two columns rather than beside
                 them: this is the exception - covering a vacancy, or work that
                 moved before the catalog caught up - and it should take a
                 decision to reach it. --}}
            @if ($others->isNotEmpty())
                <details class="rounded-lg ring-1 ring-inset ring-gray-200">
                    <summary
                        class="flex cursor-pointer items-center justify-between gap-3 rounded-lg px-4 py-2.5 text-sm hover:bg-gray-50">
                        <span class="font-medium text-gray-800">From another position</span>
                        <span class="font-data text-xs text-gray-400">{{ $others->flatten()->count() }}</span>
                    </summary>

                    {{-- Narrowed to one post before anything is shown.

                         Listing every post in the hospital and all its work
                         the moment this opens is the wall of text the fold was
                         there to prevent. So it asks first, and the three
                         selects narrow each other: a division decides which
                         sections are worth offering, a section which posts.

                         None of them carries a name. They change what is on
                         screen and nothing else - the form posts a list of
                         function ids, and a named select here would ride along
                         and mean nothing at the other end. --}}
                    <div class="space-y-3 border-t border-gray-200 p-4" x-data="borrowedFunctions()">
                        <p class="text-xs text-gray-500">
                            Work the catalog files under a post that is not yours - for covering a vacancy, or a task
                            that moved before the catalog caught up. Add one only if you actually did it.
                        </p>

                        <div class="grid gap-2 sm:grid-cols-3">
                            <label class="block">
                                <span class="sr-only">Division</span>
                                <select x-model="division"
                                    x-on:change="section = ''; position = ''; $nextTick(() => prune())"
                                    class="w-full rounded-md border-gray-300 py-1.5 text-sm">
                                    <option value="">All divisions</option>
                                    @foreach ($otherDivisions as $division)
                                        <option value="{{ $division->id }}">{{ $division->name }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="block">
                                <span class="sr-only">Section</span>
                                <select x-model="section" x-on:change="position = ''; $nextTick(() => prune())"
                                    class="w-full rounded-md border-gray-300 py-1.5 text-sm">
                                    <option value="">All sections</option>
                                    @foreach ($otherSections as $section)
                                        <option value="{{ $section->id }}"
                                            x-show="division === '' || division === '{{ $section->division_id }}'">
                                            {{ $section->name }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="block">
                                <span class="sr-only">Position</span>
                                <select x-model="position" x-on:change="$nextTick(() => prune())"
                                    class="w-full rounded-md border-gray-300 py-1.5 text-sm">
                                    <option value="">Choose a position</option>
                                    @foreach ($otherPositions as $post)
                                        <option value="{{ $post->id }}"
                                            x-show="(section === '' || section === '{{ $post->section_id }}')
                                                && (division === '' || division === '{{ $post->section?->division_id }}')">
                                            {{ $post->title }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        <p class="rounded-md bg-gray-50 p-3 text-xs text-gray-500 ring-1 ring-inset ring-gray-200"
                            x-show="position === ''">
                            Choose a position to see the work filed under it.
                        </p>

                        @foreach ($others as $postId => $items)
                            <div data-post="{{ $postId }}" x-show="position === '{{ $postId }}'" x-cloak
                                class="grid gap-1.5 sm:grid-cols-2">
                                @foreach ($items as $jobFunction)
                                    <x-ipcr.catalog-choice :function="$jobFunction" with-category />
                                @endforeach
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

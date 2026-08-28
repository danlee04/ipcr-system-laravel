@php
    // Two conditions, not one. isEditableByOwner() only asks about the status;
    // HR and administrators can now open anyone's IPCR, and a draft would
    // otherwise offer them the owner's add, edit and submit controls - every
    // one of which is refused on POST.
    $canEdit = (auth()->user()?->can('update', $ipcr) ?? false) && $ipcr->isEditableByOwner();
    $isViewingSomeoneElses = ! (auth()->user()?->can('update', $ipcr) ?? false);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                IPCR — {{ $ipcr->period->name }}
            </h2>
            <div class="flex items-center gap-3">
                <a href="{{ route('ipcrs.print', $ipcr) }}" target="_blank"
                    class="inline-flex items-center gap-2 rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 transition-colors hover:bg-gray-50">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M7 8V4h10v4M7 17H5.5A1.5 1.5 0 0 1 4 15.5v-5A1.5 1.5 0 0 1 5.5 9h13a1.5 1.5 0 0 1 1.5 1.5v5a1.5 1.5 0 0 1-1.5 1.5H17M7 14h10v6H7v-6Z" />
                    </svg>
                    Print
                </a>
                <x-ipcr.late-badge :ipcr="$ipcr" />
                <x-status-badge :status="$ipcr->status" />
            </div>
        </div>
    </x-slot>

    <x-page-container class="space-y-6">

            @if (session('status'))
                <div class="rounded-md bg-emerald-50 p-4 text-sm text-emerald-800 ring-1 ring-emerald-500/20">
                    {{ session('status') }}
                </div>
            @endif
            @if (session('error'))
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800 ring-1 ring-red-500/20">
                    {{ session('error') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800 ring-1 ring-red-500/20">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Says plainly why none of the usual controls are here. --}}
            @if ($isViewingSomeoneElses)
                <div class="rounded-md bg-sky-50 p-4 text-sm text-sky-900 ring-1 ring-sky-500/20">
                    You are viewing this IPCR read-only. Editing, assessing and approving belong to
                    {{ $ipcr->employee?->full_name ?? 'the employee' }} and the approvers in their chain.
                </div>
            @endif

            {{-- Header info --}}
            <div class="bg-white shadow-sm sm:rounded-lg ring-1 ring-gray-950/5 p-6">
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Employee</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $ipcr->employee->full_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Position</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $ipcr->position_title ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Office</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $ipcr->office_name ?? '—' }}</dd>
                    </div>
                    {{-- Named by the stage, not by a job title the hospital
                         does not use. Who takes each one follows from the org
                         chart: the Section Head assesses, the Division Head
                         gives the final approval - so the post is shown beside
                         the name, which is what tells you the routing is
                         right. --}}
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">For Assessment</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            {{ $ipcr->assessor?->nameWithPost() ?? 'Not yet routed' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">For Final Approval</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            {{ $ipcr->finalApprover?->nameWithPost() ?? 'Not yet routed' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Final Rating</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $ipcr->final_adjectival_rating ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Items --}}
            <div class="bg-white shadow-sm sm:rounded-lg ring-1 ring-gray-950/5">
                <div class="border-b border-gray-200 px-6 py-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold text-gray-900">Functions &amp; Outputs</h3>

                        {{-- What each category is worth in the final rating.
                             It is not typed anywhere: it follows from what the
                             employee actually holds, and adding a strategic
                             function changes the whole split. The old chips
                             counted the weights up to 100 instead, which can no
                             longer be anything else. --}}
                        @php
                            $present = $ipcr->items
                                ->map(fn($line) => $line->category->value)
                                ->unique()
                                ->values()
                                ->all();
                            $split = app(\App\Services\RatingCalculator::class)->weightsFor($present);
                        @endphp

                        @if ($split !== [])
                            <div class="flex flex-wrap items-center gap-2">
                                @foreach ($split as $category => $share)
                                    @php
                                        $count = $ipcr->items->filter(fn($line) => $line->category->value === $category)->count();
                                        $short = rtrim(rtrim(number_format($share, 2, '.', ''), '0'), '.');
                                    @endphp
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full bg-nav-900/5 px-2.5 py-1 font-data text-xs text-nav-900 ring-1 ring-inset ring-nav-900/10">
                                        <span
                                            class="font-sans font-medium">{{ \App\Enums\FunctionCategory::from($category)->label() }}</span>
                                        {{ $short }}% · {{ $count }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    @if ($canEdit && $ipcr->items->isNotEmpty())
                        <p class="mt-2 text-xs text-gray-500">
                            Each category is worth that much of the final rating, and its functions share it equally.
                            Add or remove one and the shares are worked out again.
                        </p>
                    @endif
                </div>

                @if ($ipcr->items->isEmpty())
                    <p class="px-6 py-8 text-center text-sm text-gray-500">No functions added yet.</p>
                @else
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Category
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Output</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Weight</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Avg. Rating
                                </th>
                                @if ($canEdit)
                                    <th class="px-6 py-3"></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach ($ipcr->items as $item)
                                <tr>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $item->category->label() }}</td>
                                    <td class="max-w-md px-6 py-4 text-sm text-gray-900">
                                        <p class="font-medium">{{ $item->output }}</p>
                                        @if ($item->success_indicator)
                                            <p class="mt-1 text-xs text-gray-500">{{ $item->success_indicator }}</p>
                                        @endif
                                        @if ($ipcr->showsAccomplishment() && $item->actual_accomplishment)
                                            <p class="mt-1 text-xs text-gray-700">
                                                <span class="font-medium">Accomplishment:</span>
                                                {{ $item->actual_accomplishment }}
                                            </p>
                                        @endif

                                        {{-- The figures behind the marks. Shown
                                             after submission too, when the
                                             editor is gone and this is the only
                                             place they appear. --}}
                                        @if ($item->measures->isNotEmpty())
                                            <div class="mt-1.5 flex flex-wrap gap-1.5">
                                                @foreach ($item->measures as $reported)
                                                    <span
                                                        class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                                                        {{ $reported->measure->label() }}
                                                        <span
                                                            class="font-data">{{ rtrim(rtrim($reported->value, '0'), '.') }}</span>
                                                        →
                                                        <span
                                                            class="font-data font-medium">{{ rtrim(rtrim($item->{$reported->measure->column()} ?? '', '0'), '.') ?: 'n/a' }}</span>
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $item->weight ?? '—' }}%</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $item->average_rating ?? '—' }}</td>
                                    @if ($canEdit)
                                        <td class="px-6 py-4 text-right text-sm">
                                            <div class="flex items-center justify-end gap-3">
                                                <button type="button"
                                                    x-on:click="$dispatch('open-modal', 'edit-item-{{ $item->id }}')"
                                                    class="font-medium text-gray-900 hover:underline">
                                                    {{ $ipcr->showsAccomplishment() && $item->jobFunction?->measures->isNotEmpty() ? 'Report' : 'Edit' }}
                                                </button>
                                                <form method="POST"
                                                    action="{{ route('ipcrs.items.destroy', [$ipcr, $item]) }}"
                                                    onsubmit="return confirm('Remove this function?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="text-red-600 hover:underline">Remove</button>
                                                </form>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            {{-- One editor per line, kept outside the table: a fixed overlay
                 has no business being a table cell's child. --}}
            @if ($canEdit)
                @foreach ($ipcr->items as $item)
                    <x-ipcr.item-editor :ipcr="$ipcr" :item="$item" />
                @endforeach
            @endif

            {{-- Add function --}}
            @if ($canEdit)
                <div class="space-y-6 bg-white shadow-sm sm:rounded-lg ring-1 ring-gray-950/5 p-6">
                    <h3 class="text-sm font-semibold text-gray-900">Add a Function</h3>

                    {{-- One list, one button. Every function used to be its own
                         form with its own button, so twelve functions meant
                         twelve clicks and twelve page loads to build one
                         IPCR. --}}
                    @php
                        $alreadyAdded = $ipcr->items->pluck('job_function_id')->filter()->all();
                        $groups = collect([
                            'Core Function' => $catalog->core,
                            'Strategic Function' => $catalog->strategic,
                            'Support Function' => $catalog->support,
                        ])->filter(fn($items) => $items->isNotEmpty());
                    @endphp

                    @if ($groups->isNotEmpty())
                        <form method="POST" action="{{ route('ipcrs.items.catalog', $ipcr) }}" class="space-y-5"
                            x-data="{ picked: 0 }">
                            @csrf

                            @foreach ($groups as $label => $items)
                                <fieldset>
                                    <legend class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500">
                                        {{ $label }}
                                    </legend>

                                    <div class="grid gap-2 lg:grid-cols-2 2xl:grid-cols-3">
                                        @foreach ($items as $jobFunction)
                                            @php $added = in_array($jobFunction->id, $alreadyAdded); @endphp

                                            <label
                                                class="flex items-start gap-3 rounded-md border px-3 py-2 {{ $added ? 'border-gray-200 bg-gray-50' : 'cursor-pointer border-gray-200 hover:border-nav-900/30 hover:bg-gray-50' }}">
                                                {{-- Already on the IPCR: ticked, fixed,
                                                     and carrying no value, so a second
                                                     copy cannot be asked for. --}}
                                                <input type="checkbox" name="job_function_ids[]"
                                                    value="{{ $jobFunction->id }}" @disabled($added) @checked($added)
                                                    x-on:change="picked += $event.target.checked ? 1 : -1"
                                                    class="mt-0.5 rounded border-gray-300 text-nav-900 focus:ring-seal">

                                                <span class="min-w-0 flex-1">
                                                    <span
                                                        class="block text-sm {{ $added ? 'text-gray-500' : 'text-gray-800' }}">{{ $jobFunction->title }}</span>
                                                    @if ($jobFunction->success_indicator)
                                                        <span
                                                            class="mt-0.5 block text-xs text-gray-500">{{ $jobFunction->success_indicator }}</span>
                                                    @endif
                                                </span>

                                                @if ($added)
                                                    <span
                                                        class="shrink-0 self-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-800 ring-1 ring-inset ring-emerald-500/20">Added</span>
                                                @endif
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            @endforeach

                            <button type="submit" x-bind:disabled="picked === 0"
                                class="rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-nav-800 disabled:cursor-not-allowed disabled:bg-gray-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-seal focus-visible:ring-offset-2">
                                <span x-show="picked === 0">Select the functions to add</span>
                                <span x-show="picked > 0" x-cloak>
                                    Add <span x-text="picked"></span>
                                    <span x-text="picked === 1 ? 'function' : 'functions'"></span>
                                </span>
                            </button>
                        </form>
                    @endif

                    <div>
                        <p class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500">Custom function (not
                            from catalog)</p>
                        {{-- Laid out as a grid rather than a stack: full-width
                             single-column inputs look stretched on a wide screen,
                             and the short fields do not need the whole span. --}}
                        <form method="POST" action="{{ route('ipcrs.items.store', $ipcr) }}"
                            class="grid gap-3 sm:grid-cols-6">
                            @csrf

                            <label class="sm:col-span-6">
                                <span class="mb-1 block text-xs font-medium text-gray-600">Category</span>
                                <select name="category" class="w-full rounded-md border-gray-300 text-sm" required>
                                    <option value="">Select category…</option>
                                    @foreach (\App\Enums\FunctionCategory::cases() as $case)
                                        <option value="{{ $case->value }}">{{ $case->label() }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="sm:col-span-3">
                                <span class="mb-1 block text-xs font-medium text-gray-600">Output / objective</span>
                                <textarea name="output" rows="3" class="w-full rounded-md border-gray-300 text-sm" required></textarea>
                            </label>

                            <label class="sm:col-span-3">
                                <span class="mb-1 block text-xs font-medium text-gray-600">Success indicator</span>
                                <textarea name="success_indicator" rows="3" class="w-full rounded-md border-gray-300 text-sm"></textarea>
                            </label>

                            <div class="sm:col-span-6">
                                <button type="submit"
                                    class="rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-nav-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-seal focus-visible:ring-offset-2">
                                    Add Custom Function
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <form method="POST" action="{{ route('ipcrs.submit', $ipcr) }}"
                    onsubmit="return confirm('Submit this IPCR for assessment? You will not be able to edit it after submitting.');">
                    @csrf
                    <button type="submit"
                        class="w-full rounded-md bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-500">
                        Submit for Assessment
                    </button>
                </form>
            @endif

            {{-- Approver controls. Renders only for whoever the IPCR is
                 currently sitting with; IpcrPolicy decides, not the view. --}}
            <x-ipcr.approver-panel :ipcr="$ipcr" />

            {{-- Approval history --}}
            @if ($ipcr->approvals->isNotEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg ring-1 ring-gray-950/5 p-6">
                    <h3 class="mb-4 text-sm font-semibold text-gray-900">Approval History</h3>
                    <ul class="space-y-3">
                        @foreach ($ipcr->approvals as $approval)
                            {{-- An administrative row has no approver: HR and
                                 administrators need not be employees, so the
                                 name comes from whichever record exists. --}}
                            <li
                                class="border-l-2 pl-3 text-sm text-gray-700 {{ $approval->isAdministrative() ? 'border-amber-400' : 'border-gray-200' }}">
                                <span class="font-medium">{{ $approval->actorName() }}</span>
                                — {{ $approval->action->label() }} ({{ $approval->stage->label() }})
                                <span class="text-gray-400">· {{ $approval->acted_at->format('M d, Y g:ia') }}</span>
                                @if ($approval->remarks)
                                    <p class="mt-1 text-gray-500">&ldquo;{{ $approval->remarks }}&rdquo;</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

</x-page-container>
</x-app-layout>

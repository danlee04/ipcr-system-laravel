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

            {{-- Where this IPCR goes, and nothing about who owns it - they are
                 reading their own page. The stage names are what tell them the
                 routing is right, and the post beside each name is what shows
                 it came from the org chart rather than somebody's choice. --}}
            <div
                class="flex flex-wrap items-center gap-x-8 gap-y-2 rounded-lg bg-white px-6 py-4 text-sm shadow-sm ring-1 ring-gray-950/5">
                <span>
                    <span class="text-xs font-medium uppercase tracking-wide text-gray-500">For Assessment</span>
                    <span class="ms-2 text-gray-900">{{ $ipcr->assessor?->nameWithPost() ?? 'Not yet routed' }}</span>
                </span>
                <span>
                    <span class="text-xs font-medium uppercase tracking-wide text-gray-500">For Final Approval</span>
                    <span
                        class="ms-2 text-gray-900">{{ $ipcr->finalApprover?->nameWithPost() ?? 'Not yet routed' }}</span>
                </span>
                @if ($ipcr->final_adjectival_rating)
                    <span>
                        <span class="text-xs font-medium uppercase tracking-wide text-gray-500">Final Rating</span>
                        <span class="ms-2 font-medium text-gray-900">{{ $ipcr->final_adjectival_rating }}</span>
                    </span>
                @endif
            </div>

            {{-- Adding comes before the list: an IPCR is built by
                 picking, and the table below is what has been picked so
                 far. --}}
            @if ($canEdit)
                <x-ipcr.function-picker :ipcr="$ipcr" :catalog="$catalog" />
            @endif

            {{-- Items, in category order: Core, then Support, then Strategic.
                 Always that order, wherever the sheet appears. --}}
            @php
                $order = [
                    \App\Enums\FunctionCategory::Core,
                    \App\Enums\FunctionCategory::Support,
                    \App\Enums\FunctionCategory::Strategic,
                ];

                // What each category is worth in the final rating. Not typed
                // anywhere: it follows from what the employee actually holds,
                // and one strategic function changes the whole split.
                $present = $ipcr->items->map(fn($line) => $line->category->value)->unique()->values()->all();
                $split = app(\App\Services\RatingCalculator::class)->weightsFor($present);
            @endphp

            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg ring-1 ring-gray-950/5">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-sm font-semibold text-gray-900">Functions &amp; Outputs</h3>

                    @if ($canEdit && $ipcr->items->isNotEmpty())
                        <p class="mt-1 text-xs text-gray-500">
                            Each category is worth a share of the final rating, and its functions split that share
                            equally. Add or remove one and the shares are worked out again.
                        </p>
                    @endif
                </div>

                @if ($ipcr->items->isEmpty())
                    <p class="px-6 py-8 text-center text-sm text-gray-500">No functions added yet.</p>
                @else
                    @foreach ($order as $category)
                        @php
                            $lines = $ipcr->items->filter(fn($line) => $line->category === $category);
                            $share = $split[$category->value] ?? null;
                        @endphp

                        @continue($lines->isEmpty())

                        {{-- The category is said once, over its own block,
                             rather than repeated down a column of its own. --}}
                        <div
                            class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-4 py-2.5">
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $category->badgeClasses() }}">
                                {{ $category->label() }}
                            </span>
                            <span class="font-data text-xs text-gray-500">
                                @if ($share !== null)
                                    {{ rtrim(rtrim(number_format($share, 2, '.', ''), '0'), '.') }}% of the final
                                    rating &middot;
                                @endif
                                {{ $lines->count() }} {{ \Illuminate\Support\Str::plural('function', $lines->count()) }}
                            </span>
                        </div>

                        <table class="w-full table-fixed divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th
                                        class="w-[27%] px-4 py-2 text-left text-xs font-medium uppercase text-gray-400">
                                        Output</th>
                                    <th
                                        class="w-[27%] px-4 py-2 text-left text-xs font-medium uppercase text-gray-400">
                                        Success Indicator</th>
                                    <th
                                        class="w-[30%] px-4 py-2 text-left text-xs font-medium uppercase text-gray-400">
                                        Actual Accomplishment</th>
                                    <th
                                        class="w-[8%] px-4 py-2 text-left text-xs font-medium uppercase text-gray-400">
                                        Avg.</th>
                                    @if ($canEdit)
                                        <th class="w-[8%] px-4 py-2"></th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @foreach ($lines as $item)
                                    <tr>
                                        {{-- Three long sentences, three columns,
                                             each cut to two lines with the rest on
                                             hover. Whole, they set the height of
                                             the row to the longest thing anybody
                                             ever wrote. --}}
                                        <td class="px-4 py-3 align-top text-sm">
                                            <p class="line-clamp-2 font-medium text-gray-900"
                                                title="{{ $item->output }}">{{ $item->output }}</p>
                                        </td>
                                        <td class="px-4 py-3 align-top text-sm">
                                            <p class="line-clamp-2 text-gray-600"
                                                title="{{ $item->success_indicator }}">
                                                {{ $item->success_indicator ?: '—' }}</p>
                                        </td>
                                        <td class="px-4 py-3 align-top text-sm">
                                            @if ($ipcr->showsAccomplishment())
                                                <p class="line-clamp-2 text-gray-700"
                                                    title="{{ $item->actual_accomplishment }}">
                                                    {{ $item->actual_accomplishment ?: '—' }}</p>

                                                {{-- The figures behind the marks.
                                                     Shown after submission too,
                                                     when the editor is gone and
                                                     this is the only place they
                                                     appear. --}}
                                                @if ($item->measures->isNotEmpty())
                                                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                                                        @foreach ($item->measures as $reported)
                                                            <span
                                                                class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                                                                {{ strtoupper($reported->measure->key()) }}
                                                                <span
                                                                    class="font-data">{{ rtrim(rtrim($reported->value, '0'), '.') }}</span>
                                                                &rarr;
                                                                <span
                                                                    class="font-data font-medium">{{ rtrim(rtrim($item->{$reported->measure->column()} ?? '', '0'), '.') ?: 'n/a' }}</span>
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            @else
                                                <span class="text-gray-400">&mdash;</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 align-top font-data text-sm text-gray-900">
                                            {{ $item->average_rating !== null ? number_format((float) $item->average_rating, 2) : '—' }}
                                        </td>
                                        @if ($canEdit)
                                            <td class="px-4 py-3 align-top text-right text-sm">
                                                <div class="flex flex-col items-end gap-1">
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
                    @endforeach
                @endif
            </div>

            {{-- One editor per line, kept outside the table: a fixed overlay
                 has no business being a table cell's child. --}}
            @if ($canEdit)
                @foreach ($ipcr->items as $item)
                    <x-ipcr.item-editor :ipcr="$ipcr" :item="$item" />
                @endforeach
            @endif

            @if ($canEdit)
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

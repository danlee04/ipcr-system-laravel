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

                        {{-- The running total per category. Submission requires
                             each one to reach 100%, so the owner needs to see
                             where they stand while they are still building. --}}
                        @php
                            $weightTotals = $ipcr->weightTotalsByCategory();
                            $badTotals = $ipcr->categoriesWithBadWeightTotals();
                        @endphp

                        @if ($weightTotals !== [])
                            <div class="flex flex-wrap items-center gap-2">
                                @foreach ($weightTotals as $category => $total)
                                    @php $short = rtrim(rtrim(number_format($total, 2, '.', ''), '0'), '.'); @endphp
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-data text-xs ring-1 ring-inset {{ isset($badTotals[$category]) ? 'bg-amber-50 text-amber-800 ring-amber-500/30' : 'bg-emerald-50 text-emerald-800 ring-emerald-500/30' }}">
                                        <span
                                            class="font-sans font-medium">{{ \App\Enums\FunctionCategory::from($category)->label() }}</span>
                                        {{ $short }} of 100%
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    @if ($badTotals !== [] && $canEdit)
                        <p class="mt-2 text-xs text-amber-800">
                            Each category must total 100% before this IPCR can be submitted.
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

                    {{-- Three groups, matching the three kinds of work. A
                         function open to everyone appears under its own
                         category rather than in a pool of its own. --}}
                    @foreach (['core' => $catalog->core, 'strategic' => $catalog->strategic, 'support' => $catalog->support] as $key => $items)
                        @if ($items->isNotEmpty())
                            <div>
                                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500">
                                    {{ ucfirst($key) }} — from catalog</p>
                                <div class="grid gap-2 lg:grid-cols-2 2xl:grid-cols-3">
                                    @foreach ($items as $jobFunction)
                                        <form method="POST" action="{{ route('ipcrs.items.store', $ipcr) }}"
                                            class="flex items-center justify-between gap-4 rounded-md border border-gray-200 px-3 py-2">
                                            @csrf
                                            <input type="hidden" name="job_function_id"
                                                value="{{ $jobFunction->id }}">
                                            <input type="hidden" name="category"
                                                value="{{ $jobFunction->category->value }}">
                                            <input type="hidden" name="output" value="{{ $jobFunction->title }}">
                                            <input type="hidden" name="success_indicator"
                                                value="{{ $jobFunction->success_indicator }}">
                                            <span class="text-sm text-gray-700">{{ $jobFunction->title }}</span>
                                            <button type="submit"
                                                class="shrink-0 rounded-md bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-900 hover:bg-gray-200">+
                                                Add</button>
                                        </form>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach

                    <div>
                        <p class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500">Custom function (not
                            from catalog)</p>
                        {{-- Laid out as a grid rather than a stack: full-width
                             single-column inputs look stretched on a wide screen,
                             and the short fields do not need the whole span. --}}
                        <form method="POST" action="{{ route('ipcrs.items.store', $ipcr) }}"
                            class="grid gap-3 sm:grid-cols-6">
                            @csrf

                            <label class="sm:col-span-4">
                                <span class="mb-1 block text-xs font-medium text-gray-600">Category</span>
                                <select name="category" class="w-full rounded-md border-gray-300 text-sm" required>
                                    <option value="">Select category…</option>
                                    @foreach (\App\Enums\FunctionCategory::cases() as $case)
                                        <option value="{{ $case->value }}">{{ $case->label() }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="sm:col-span-2">
                                <span class="mb-1 block text-xs font-medium text-gray-600">Weight %</span>
                                <input type="number" step="0.01" min="0" max="100" name="weight"
                                    class="w-full rounded-md border-gray-300 text-sm">
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

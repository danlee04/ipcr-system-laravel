<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                IPCR — {{ $ipcr->period->name }}
            </h2>
            <x-status-badge :status="$ipcr->status" />
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

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
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Assessor</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $ipcr->assessor?->full_name ?? 'Not yet routed' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Final Approver</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            {{ $ipcr->finalApprover?->full_name ?? 'Not yet routed' }}</dd>
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
                    <h3 class="text-sm font-semibold text-gray-900">Functions &amp; Outputs</h3>
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
                                @if ($ipcr->isEditableByOwner())
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
                                        @if ($item->actual_accomplishment)
                                            <p class="mt-1 text-xs text-gray-700">
                                                <span class="font-medium">Accomplishment:</span>
                                                {{ $item->actual_accomplishment }}
                                            </p>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $item->weight ?? '—' }}%</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $item->average_rating ?? '—' }}</td>
                                    @if ($ipcr->isEditableByOwner())
                                        <td class="space-y-1 px-6 py-4 text-right text-sm">
                                            <details class="inline-block text-left">
                                                <summary class="cursor-pointer text-gray-900 hover:underline">Edit
                                                </summary>
                                                <form method="POST"
                                                    action="{{ route('ipcrs.items.update', [$ipcr, $item]) }}"
                                                    class="mt-2 w-72 space-y-2">
                                                    @csrf
                                                    @method('PUT')
                                                    <textarea name="output" rows="2" class="w-full rounded-md border-gray-300 text-sm" required>{{ $item->output }}</textarea>
                                                    <textarea name="success_indicator" rows="2" class="w-full rounded-md border-gray-300 text-sm"
                                                        placeholder="Success indicator">{{ $item->success_indicator }}</textarea>
                                                    <input type="number" step="0.01" min="0" max="100"
                                                        name="weight" value="{{ $item->weight }}"
                                                        placeholder="Weight %"
                                                        class="w-full rounded-md border-gray-300 text-sm">
                                                    <textarea name="actual_accomplishment" rows="2" class="w-full rounded-md border-gray-300 text-sm"
                                                        placeholder="Actual accomplishment">{{ $item->actual_accomplishment }}</textarea>
                                                    <button type="submit"
                                                        class="rounded-md bg-gray-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-700">Save</button>
                                                </form>
                                            </details>
                                            <form method="POST"
                                                action="{{ route('ipcrs.items.destroy', [$ipcr, $item]) }}"
                                                onsubmit="return confirm('Remove this function?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="text-red-600 hover:underline">Remove</button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            {{-- Add function --}}
            @if ($ipcr->isEditableByOwner())
                <div class="space-y-6 bg-white shadow-sm sm:rounded-lg ring-1 ring-gray-950/5 p-6">
                    <h3 class="text-sm font-semibold text-gray-900">Add a Function</h3>

                    @foreach (['core' => $catalog->core, 'strategic' => $catalog->strategic, 'support' => $catalog->support, 'common' => $catalog->common] as $key => $items)
                        @if ($items->isNotEmpty())
                            <div>
                                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500">
                                    {{ ucfirst($key) }} — from catalog</p>
                                <div class="space-y-2">
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
                                            <input type="hidden" name="weight"
                                                value="{{ $jobFunction->default_weight }}">
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
                        <form method="POST" action="{{ route('ipcrs.items.store', $ipcr) }}" class="space-y-3">
                            @csrf
                            <select name="category" class="w-full rounded-md border-gray-300 text-sm" required>
                                <option value="">Select category…</option>
                                @foreach (\App\Enums\FunctionCategory::cases() as $case)
                                    <option value="{{ $case->value }}">{{ $case->label() }}</option>
                                @endforeach
                            </select>
                            <textarea name="output" rows="2" class="w-full rounded-md border-gray-300 text-sm"
                                placeholder="Output / objective" required></textarea>
                            <textarea name="success_indicator" rows="2" class="w-full rounded-md border-gray-300 text-sm"
                                placeholder="Success indicator"></textarea>
                            <input type="number" step="0.01" min="0" max="100" name="weight"
                                placeholder="Weight %" class="w-full rounded-md border-gray-300 text-sm">
                            <button type="submit"
                                class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">Add
                                Custom Function</button>
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

            {{-- Approval history --}}
            @if ($ipcr->approvals->isNotEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg ring-1 ring-gray-950/5 p-6">
                    <h3 class="mb-4 text-sm font-semibold text-gray-900">Approval History</h3>
                    <ul class="space-y-3">
                        @foreach ($ipcr->approvals as $approval)
                            <li class="border-l-2 border-gray-200 pl-3 text-sm text-gray-700">
                                <span class="font-medium">{{ $approval->approver->full_name }}</span>
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

        </div>
    </div>
</x-app-layout>

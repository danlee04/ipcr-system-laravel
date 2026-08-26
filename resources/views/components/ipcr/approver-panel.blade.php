@props(['ipcr'])

@php
    $user = auth()->user();
    $canAssess = $user?->can('assess', $ipcr) ?? false;
    $canFinalize = $user?->can('finalize', $ipcr) ?? false;
@endphp

@if ($canAssess || $canFinalize)
    <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-nav-900/10">
        <div class="border-b border-gray-200 bg-nav-900 px-6 py-4">
            <h3 class="text-sm font-semibold text-white">
                {{ $canAssess ? 'Your assessment' : 'Final rating' }}
            </h3>
            <p class="mt-0.5 text-xs text-nav-300">
                @if ($canAssess)
                    Rate every function from 1 to 5 on quality, efficiency and timeliness. Save as often as you
                    like — the assessment is only complete when you say so.
                @else
                    Every function has been rated at the assessment stage. Approving makes this rating permanent.
                @endif
            </p>
        </div>

        @if ($canAssess)
            <form method="POST" action="{{ route('ipcrs.ratings.update', $ipcr) }}">
                @csrf
                @method('PUT')

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Function</th>
                                <th
                                    class="px-3 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Weight</th>
                                <th
                                    class="px-3 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Q</th>
                                <th
                                    class="px-3 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">
                                    E</th>
                                <th
                                    class="px-3 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">
                                    T</th>
                                <th
                                    class="px-3 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Average</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach ($ipcr->items as $item)
                                <tr>
                                    <td class="px-6 py-3 text-sm">
                                        <span
                                            class="inline-flex items-center rounded-full px-2 py-0.5 text-[0.625rem] font-medium uppercase tracking-wide ring-1 ring-inset {{ $item->category->badgeClasses() }}">
                                            {{ $item->category->label() }}
                                        </span>
                                        <span class="mt-1 block text-gray-900">{{ $item->output }}</span>
                                    </td>
                                    <td class="px-3 py-3 text-center font-data text-sm text-gray-600">
                                        {{ $item->weight ? rtrim(rtrim($item->weight, '0'), '.') . '%' : '—' }}
                                    </td>
                                    @foreach (['quality' => $item->quality_rating, 'efficiency' => $item->efficiency_rating, 'timeliness' => $item->timeliness_rating] as $mark => $current)
                                        <td class="px-3 py-3 text-center">
                                            <input type="number" step="0.01" min="1" max="5"
                                                name="ratings[{{ $item->id }}][{{ $mark }}]"
                                                value="{{ $current !== null ? rtrim(rtrim($current, '0'), '.') : '' }}"
                                                aria-label="{{ ucfirst($mark) }} for {{ $item->output }}"
                                                class="w-20 rounded-md border-gray-300 text-center font-data text-sm">
                                        </td>
                                    @endforeach
                                    <td class="px-3 py-3 text-center font-data text-sm font-medium text-gray-900">
                                        {{ $item->average_rating !== null ? number_format((float) $item->average_rating, 2) : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4">
                    <button type="submit"
                        class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        Save ratings
                    </button>
                </div>
            </form>
        @endif

        <div class="flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 px-6 py-4">
            <button type="button" x-data x-on:click="$dispatch('open-modal', 'return-ipcr')"
                class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-red-700 ring-1 ring-inset ring-red-300 hover:bg-red-50">
                Return for revision
            </button>

            @if ($canAssess)
                <form method="POST" action="{{ route('ipcrs.assess', $ipcr) }}"
                    onsubmit="return confirm('Complete the assessment and send this on for final approval?');">
                    @csrf
                    <button type="submit"
                        class="rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white hover:bg-nav-800">
                        Complete assessment
                    </button>
                </form>
            @else
                <form method="POST" action="{{ route('ipcrs.approve', $ipcr) }}"
                    onsubmit="return confirm('Approve this IPCR? The final rating becomes permanent.');">
                    @csrf
                    <button type="submit"
                        class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                        Give final approval
                    </button>
                </form>
            @endif
        </div>
    </div>

    <x-modal name="return-ipcr" focusable max-width="lg">
        <form method="POST" action="{{ route('ipcrs.return', $ipcr) }}" class="space-y-4 p-6">
            @csrf
            <h2 class="text-lg font-semibold text-gray-900">Return for revision</h2>
            <p class="text-sm text-gray-600">
                {{ $ipcr->employee?->full_name }} will be able to edit this IPCR again and resubmit it.
            </p>

            <label class="block">
                <span class="mb-1 block text-sm font-medium text-gray-700">What needs to change?</span>
                <textarea name="remarks" rows="4" required maxlength="2000"
                    class="w-full rounded-md border-gray-300 text-sm"></textarea>
            </label>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" x-on:click="$dispatch('close-modal', 'return-ipcr')"
                    class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Cancel</button>
                <button type="submit"
                    class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500">Return
                    it</button>
            </div>
        </form>
    </x-modal>
@endif

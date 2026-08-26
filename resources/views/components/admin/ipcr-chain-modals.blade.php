@props(['ipcr', 'employees'])

{{-- The two administrative actions on one IPCR.

     They are mutually exclusive by status, and IpcrPolicy is what decides:
     an approved IPCR can only be reopened, anything earlier can only be
     rerouted. The view asks rather than deciding for itself, so the buttons
     and the routes can never disagree. --}}

@can('reroute', $ipcr)
    <x-modal name="chain-{{ $ipcr->id }}" focusable max-width="lg">
        <form method="POST" action="{{ route('admin.ipcrs.chain', $ipcr) }}" class="space-y-4 p-6">
            @csrf
            @method('PUT')

            <h2 class="text-lg font-semibold text-gray-900">
                Approval chain — {{ $ipcr->employee?->full_name }}
            </h2>
            <p class="text-sm text-gray-600">
                Routing is normally automatic: the Section Head assesses and the Division Head gives the final
                approval. This IPCR is here because the org chart cannot answer that on its own — so it is set by
                hand, and stays set on every resubmission.
            </p>

            <label class="block">
                <span class="mb-1 block text-sm font-medium text-gray-700">For Assessment</span>
                <select name="assessor_employee_id" required class="w-full rounded-md border-gray-300 text-sm">
                    <option value="">— Choose —</option>
                    @foreach ($employees as $candidate)
                        @continue($candidate->id === $ipcr->employee_id)
                        <option value="{{ $candidate->id }}" @selected(old('assessor_employee_id', $ipcr->assessor_employee_id) == $candidate->id)>
                            {{ $candidate->nameWithPost() }}
                        </option>
                    @endforeach
                </select>
                <span class="mt-1 block text-xs text-gray-500">Normally the Section Head. They enter the quality,
                    efficiency and timeliness marks.</span>
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-medium text-gray-700">For Final Approval</span>
                <select name="final_approver_employee_id" required class="w-full rounded-md border-gray-300 text-sm">
                    <option value="">— Choose —</option>
                    @foreach ($employees as $candidate)
                        @continue($candidate->id === $ipcr->employee_id)
                        <option value="{{ $candidate->id }}" @selected(old('final_approver_employee_id', $ipcr->final_approver_employee_id) == $candidate->id)>
                            {{ $candidate->nameWithPost() }}
                        </option>
                    @endforeach
                </select>
                <span class="mt-1 block text-xs text-gray-500">Normally the Division Head. May be the same person — a
                    Division Head's own IPCR is assessed and approved by the Chief of Hospital.</span>
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-medium text-gray-700">Reason</span>
                <textarea name="reason" rows="2" required maxlength="500"
                    class="w-full rounded-md border-gray-300 text-sm"
                    placeholder="Why is this being set by hand?">{{ old('reason') }}</textarea>
                <span class="mt-1 block text-xs text-gray-500">Goes into the IPCR's approval history.</span>
            </label>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" x-on:click="$dispatch('close-modal', 'chain-{{ $ipcr->id }}')"
                    class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Cancel</button>
                <button type="submit"
                    class="rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white hover:bg-nav-800">Save
                    chain</button>
            </div>
        </form>

        {{-- The way back out. Once a section has a head again, the IPCR should
             follow the org chart like every other one; without this it would
             go on ignoring that head forever. --}}
        @can('releaseChain', $ipcr)
            <div class="border-t border-gray-200 bg-gray-50 p-6">
                <form method="POST" action="{{ route('admin.ipcrs.chain.release', $ipcr) }}" class="space-y-3"
                    onsubmit="return confirm('Hand this IPCR back to automatic routing?');">
                    @csrf
                    @method('DELETE')

                    <h3 class="text-sm font-semibold text-gray-900">Use automatic routing instead</h3>
                    <p class="text-xs text-gray-600">
                        Clears the two names above. The next submission works the chain out from the org chart again.
                    </p>

                    <label class="block">
                        <span class="sr-only">Reason</span>
                        <input type="text" name="reason" required maxlength="500"
                            class="w-full rounded-md border-gray-300 text-sm" placeholder="Why hand it back?">
                    </label>

                    <button type="submit"
                        class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        Hand back to automatic
                    </button>
                </form>
            </div>
        @endcan
    </x-modal>
@endcan

@can('reopen', $ipcr)
    <x-modal name="reopen-{{ $ipcr->id }}" focusable max-width="lg">
        <form method="POST" action="{{ route('admin.ipcrs.reopen', $ipcr) }}" class="space-y-4 p-6">
            @csrf

            <h2 class="text-lg font-semibold text-gray-900">
                Reopen — {{ $ipcr->employee?->full_name }}
            </h2>
            <p class="text-sm text-gray-600">
                This IPCR was approved at
                <span class="font-data font-medium">{{ number_format((float) $ipcr->final_numerical_rating, 3) }}</span>
                ({{ $ipcr->final_adjectival_rating }}). Reopening clears that rating — it is recomputed when the IPCR
                is approved again. The marks on each line are kept.
            </p>

            <fieldset class="space-y-2">
                <legend class="mb-1 text-sm font-medium text-gray-700">Send it back to</legend>

                <label class="flex gap-3 rounded-md p-3 ring-1 ring-inset ring-gray-200 hover:bg-gray-50">
                    <input type="radio" name="target" value="assessment" checked class="mt-1">
                    <span class="text-sm">
                        <span class="block font-medium text-gray-900">Assessment</span>
                        <span class="block text-gray-500">
                            {{ $ipcr->assessor?->nameWithPost() ?? 'Nobody is routed for assessment' }} re-enters the
                            marks. Use this when a rating is wrong.
                        </span>
                    </span>
                </label>

                <label class="flex gap-3 rounded-md p-3 ring-1 ring-inset ring-gray-200 hover:bg-gray-50">
                    <input type="radio" name="target" value="employee" class="mt-1">
                    <span class="text-sm">
                        <span class="block font-medium text-gray-900">The employee</span>
                        <span class="block text-gray-500">
                            {{ $ipcr->employee?->full_name }} can edit the IPCR and submit it again. Use this when the
                            targets themselves are wrong.
                        </span>
                    </span>
                </label>
            </fieldset>

            <label class="block">
                <span class="mb-1 block text-sm font-medium text-gray-700">Reason</span>
                <textarea name="reason" rows="2" required maxlength="500"
                    class="w-full rounded-md border-gray-300 text-sm"
                    placeholder="Why is an approved IPCR being reopened?">{{ old('reason') }}</textarea>
                <span class="mt-1 block text-xs text-gray-500">Goes into the IPCR's approval history, along with the
                    rating being undone.</span>
            </label>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" x-on:click="$dispatch('close-modal', 'reopen-{{ $ipcr->id }}')"
                    class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Cancel</button>
                <button type="submit"
                    class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500">Reopen
                    IPCR</button>
            </div>
        </form>
    </x-modal>
@endcan

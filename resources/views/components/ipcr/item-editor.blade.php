@props(['ipcr', 'item'])

@php
    use App\Enums\MeasureAnswer;

    $function = $item->jobFunction;
    $rubric = $function?->measures ?? collect();

    $reports = $ipcr->showsAccomplishment();

    // With a template the sentence is written from the figures, so there is
    // nothing left for the employee to type. Without one they still say it in
    // their own words - the rubric only supplies the marks.
    $writesItself = $reports && $rubric->isNotEmpty()
        && trim((string) $function->accomplishment_template) !== '';

    $name = 'edit-item-' . $item->id;

    $tidy = fn ($value) => $value === null ? '' : rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
@endphp

<x-modal :name="$name" focusable max-width="4xl">
    <form method="POST" action="{{ route('ipcrs.items.update', [$ipcr, $item]) }}" class="space-y-6 p-6">
        @csrf
        @method('PUT')

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Edit this function</h2>
                <p class="mt-0.5 text-sm text-gray-600">{{ $item->category->label() }}</p>
            </div>
            @if ($rubric->isNotEmpty())
                <span
                    class="inline-flex items-center rounded-full bg-nav-900/5 px-2.5 py-1 text-xs font-medium text-nav-900 ring-1 ring-inset ring-nav-900/10">
                    Graded from figures
                </span>
            @endif
        </div>

        <div class="grid gap-4 sm:grid-cols-6">
            <label class="sm:col-span-4">
                <span class="mb-1 block text-xs font-medium text-gray-600">Output / objective</span>
                <textarea name="output" rows="2" required class="w-full rounded-md border-gray-300 text-sm">{{ $item->output }}</textarea>
            </label>

            <label class="sm:col-span-2">
                <span class="mb-1 block text-xs font-medium text-gray-600">Weight %</span>
                <input type="number" step="0.01" min="0" max="100" name="weight"
                    value="{{ $tidy($item->weight) }}" class="w-full rounded-md border-gray-300 text-sm">
            </label>

            <label class="sm:col-span-6">
                <span class="mb-1 block text-xs font-medium text-gray-600">Success indicator</span>
                <textarea name="success_indicator" rows="2" class="w-full rounded-md border-gray-300 text-sm">{{ $item->success_indicator }}</textarea>
            </label>
        </div>

        @if ($reports)
            <div class="space-y-4 border-t border-gray-200 pt-6">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">What you accomplished</h3>
                    @if ($rubric->isNotEmpty())
                        <p class="mt-0.5 text-sm text-gray-600">
                            Type the figures. The mark comes from the levels below
                            @if ($writesItself)
                                , and the sentence on the form is written from them
                            @endif
                            . Leave a measure blank if it does not apply.
                        </p>
                    @endif
                </div>

                @foreach ($rubric as $measure)
                    @php
                        $reported = $item->reportedFor($measure->measure);
                        $field = 'reported[' . $measure->measure->value . ']';
                        $earned = $item->{$measure->measure->column()};
                    @endphp

                    <div class="rounded-lg p-4 ring-1 ring-gray-200">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ $measure->measure->label() }}</p>
                                <p class="text-xs text-gray-500">{{ $measure->answer->label() }}</p>
                            </div>

                            <div class="flex items-center gap-2">
                                @switch($measure->answer)
                                    @case(MeasureAnswer::Descriptor)
                                        <select name="{{ $field }}[value]"
                                            class="w-72 rounded-md border-gray-300 text-sm">
                                            <option value="">Not applicable</option>
                                            @foreach ($measure->bands->sortByDesc('level') as $band)
                                                <option value="{{ $band->level }}"
                                                    @selected($reported && (int) $reported->value === $band->level)>
                                                    {{ $band->level }} — {{ $band->description }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @break

                                    @case(MeasureAnswer::Count)
                                        <input type="number" step="0.01" min="0" name="{{ $field }}[count]"
                                            value="{{ $tidy($reported?->reported_count) }}"
                                            aria-label="{{ $measure->measure->label() }} count"
                                            class="w-24 rounded-md border-gray-300 text-center font-data text-sm">
                                        <span class="text-sm text-gray-500">of</span>
                                        <input type="number" step="0.01" min="0" name="{{ $field }}[total]"
                                            value="{{ $tidy($reported?->reported_total) }}"
                                            aria-label="{{ $measure->measure->label() }} total"
                                            class="w-24 rounded-md border-gray-300 text-center font-data text-sm">
                                    @break

                                    @default
                                        <input type="number" step="0.01" name="{{ $field }}[value]"
                                            value="{{ $tidy($reported?->value) }}"
                                            aria-label="{{ $measure->measure->label() }}"
                                            class="w-28 rounded-md border-gray-300 text-center font-data text-sm">
                                        @if ($measure->unit)
                                            <span class="text-sm text-gray-500">{{ $measure->unit }}</span>
                                        @endif
                                @endswitch

                                <span
                                    class="ms-2 inline-flex w-16 items-center justify-center rounded-md bg-gray-100 px-2 py-1 font-data text-sm text-gray-700">
                                    {{ $earned === null ? 'n/a' : $tidy($earned) }}
                                </span>
                            </div>
                        </div>

                        {{-- The ladder, so the figure is typed against something
                             visible rather than guessed at. --}}
                        <div class="mt-3 grid gap-1.5 sm:grid-cols-5">
                            @foreach ($measure->bands->sortByDesc('level') as $band)
                                <div
                                    class="rounded-md px-2 py-1.5 text-xs ring-1 ring-inset {{ $earned !== null && (int) $earned === $band->level ? 'bg-emerald-50 text-emerald-900 ring-emerald-500/30' : 'bg-gray-50 text-gray-600 ring-gray-200' }}">
                                    <span class="font-data font-medium">{{ $band->level }}</span>
                                    <span class="ms-1">{{ $band->description }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                @unless ($writesItself)
                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-gray-600">Actual accomplishment</span>
                        <textarea name="actual_accomplishment" rows="3" class="w-full rounded-md border-gray-300 text-sm">{{ $item->actual_accomplishment }}</textarea>
                    </label>
                @else
                    <p class="rounded-md bg-gray-50 p-3 text-sm text-gray-700">
                        <span class="font-medium">On the form:</span>
                        {{ $item->actual_accomplishment ?: 'nothing yet — report the figures above.' }}
                    </p>
                @endunless
            </div>
        @endif

        <div class="flex justify-end gap-3 border-t border-gray-200 pt-4">
            <button type="button" x-on:click="$dispatch('close-modal', '{{ $name }}')"
                class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 transition-colors hover:bg-gray-50">
                Cancel
            </button>
            <button type="submit"
                class="rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-nav-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-seal focus-visible:ring-offset-2">
                Save
            </button>
        </div>
    </form>
</x-modal>

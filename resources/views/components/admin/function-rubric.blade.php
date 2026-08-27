@props(['function' => null])

@php
    use App\Enums\MeasureAnswer;
    use App\Enums\RatingMeasure;
    use App\Models\FunctionMeasure;

    // What is already on the function, so the panels open on what is there
    // rather than resetting every measure to n/a.
    $saved = $function?->measures->keyBy(fn ($m) => $m->measure->value) ?? collect();
@endphp

{{-- How this function is graded.

     Each measure is asked separately, because they are different questions: a
     function can be graded on a typed percentage for Efficiency and on a
     picked descriptor for Quality. A measure left blank is n/a - most outputs
     have no Timeliness at all - and the rating averages only what applies.

     The whole rubric is optional. Functions predate it, and one without a
     rubric is marked by hand the way everything used to be. --}}
<div class="space-y-3" x-data="functionRubric()">
    <div class="flex items-baseline justify-between gap-3">
        <h3 class="text-sm font-semibold text-gray-900">Description of Rating</h3>
        <span class="text-xs text-gray-500">A measure left blank is n/a. Each one is graded its own way.</span>
    </div>

    @foreach (RatingMeasure::cases() as $measure)
        @php
            $rubric = $saved->get($measure->value);
            $bands = $rubric?->bands->keyBy('level') ?? collect();
            $answer = old("rubric.{$measure->value}.answer", $rubric?->answer?->value ?? MeasureAnswer::Descriptor->value);
            $unit = old("rubric.{$measure->value}.unit", $rubric?->unit ?? '%');
        @endphp

        <details class="rounded-lg ring-1 ring-inset ring-gray-200" @if ($rubric) open @endif
            x-data="{ answer: '{{ $answer }}' }"
            x-bind:data-numeric="answer !== 'descriptor'">
            <summary class="flex cursor-pointer items-baseline justify-between gap-3 px-4 py-2.5 text-sm hover:bg-gray-50">
                <span class="font-medium text-gray-800">
                    {{ $measure->label() }} <span class="font-data text-xs text-gray-400">({{ strtoupper($measure->key()) }})</span>
                </span>
                <span class="text-xs text-gray-400">{{ $rubric ? 'rated' : 'n/a — click to use' }}</span>
            </summary>

            <div class="space-y-3 border-t border-gray-200 p-4">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-[0.625rem] font-bold uppercase tracking-wider text-gray-400">Answered by</span>

                    <select name="rubric[{{ $measure->value }}][answer]" x-model="answer"
                        class="rounded-md border-gray-300 py-1 text-sm">
                        @foreach (MeasureAnswer::cases() as $option)
                            <option value="{{ $option->value }}" @selected($answer === $option->value)>
                                {{ $option->label() }}
                            </option>
                        @endforeach
                    </select>

                    {{-- A count divides, so it is a percentage by construction
                         and has no unit to pick. --}}
                    <template x-if="answer === 'number'">
                        <span class="flex items-center gap-2">
                            <span class="text-[0.625rem] font-bold uppercase tracking-wider text-gray-400">in</span>
                            <select name="rubric[{{ $measure->value }}][unit]"
                                class="w-24 rounded-md border-gray-300 py-1 text-sm">
                                @foreach (FunctionMeasure::UNITS as $option)
                                    <option value="{{ $option }}" @selected($unit === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                        </span>
                    </template>

                    <span class="text-xs italic text-gray-400" x-show="answer === 'count'" x-cloak>
                        graded on the percentage the two numbers make — the bands below are in %
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[0.625rem] font-bold uppercase tracking-wider text-gray-400">
                                <th class="w-10 pb-1">Level</th>
                                <th class="pb-1">Description</th>
                                <th class="w-24 pb-1 text-center" x-show="answer !== 'descriptor'" x-cloak>From</th>
                                <th class="w-24 pb-1 text-center" x-show="answer !== 'descriptor'" x-cloak>To</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (FunctionMeasure::LEVELS as $level)
                                @php $band = $bands->get($level); @endphp
                                <tr>
                                    <td class="py-1 text-center font-data text-base font-bold text-nav-900">{{ $level }}</td>
                                    <td class="py-1 pe-2">
                                        <input type="text"
                                            name="rubric[{{ $measure->value }}][{{ $level }}][description]"
                                            value="{{ old("rubric.{$measure->value}.{$level}.description", $band?->description) }}"
                                            class="w-full rounded-md border-gray-300 py-1 text-sm">
                                    </td>
                                    <td class="py-1 pe-2" x-show="answer !== 'descriptor'" x-cloak>
                                        <input type="number" step="0.01"
                                            name="rubric[{{ $measure->value }}][{{ $level }}][min]"
                                            value="{{ old("rubric.{$measure->value}.{$level}.min", $band?->min_value) }}"
                                            class="w-full rounded-md border-gray-300 py-1 text-center font-data text-sm">
                                    </td>
                                    <td class="py-1" x-show="answer !== 'descriptor'" x-cloak>
                                        <input type="number" step="0.01"
                                            name="rubric[{{ $measure->value }}][{{ $level }}][max]"
                                            value="{{ old("rubric.{$measure->value}.{$level}.max", $band?->max_value) }}"
                                            class="w-full rounded-md border-gray-300 py-1 text-center font-data text-sm">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="text-xs text-gray-500" x-show="answer !== 'descriptor'" x-cloak>
                    A blank From means “anything below To”, a blank To means “anything above From”. Bands are read
                    from 5 down, so a timeliness scale is written the other way round — level 5 up to 90 days,
                    level 1 from 181 onwards.
                </p>
            </div>
        </details>
    @endforeach

    {{-- Only a reported figure can fill a sentence, so this appears only once
         a measure is answered by a number. --}}
    <div class="rounded-lg bg-gray-50 p-4 ring-1 ring-inset ring-gray-200" x-show="placeholders.length" x-cloak>
        <label class="block">
            <span class="mb-1 block text-sm font-medium text-gray-700">Accomplishment wording</span>
            <textarea name="accomplishment_template" rows="2" class="w-full rounded-md border-gray-300 text-sm"
                placeholder="{e}% of DTR with complete attachments are submitted every 5th day of the ensuing month">{{ old('accomplishment_template', $function?->accomplishment_template) }}</textarea>
        </label>
        <p class="mt-1 text-xs text-gray-500">
            Copy the success indicator and replace each target figure with its placeholder. Available here:
            <span class="font-data text-gray-700" x-text="placeholders.join('  ')"></span>
        </p>
    </div>
</div>

@once
    @push('scripts')
        <script>
            // Mirrors JobFunction::placeholders(). The list follows the panels
            // rather than the saved rubric, so it is right while still typing.
            function functionRubric() {
                return {
                    placeholders: [],

                    init() {
                        this.refresh();
                        this.$el.addEventListener('change', () => this.refresh());
                    },

                    refresh() {
                        const numeric = [];

                        this.$el.querySelectorAll('details').forEach((panel) => {
                            const select = panel.querySelector('select[name$="[answer]"]');
                            if (!select || select.value === 'descriptor') return;

                            const match = select.name.match(/rubric\[(\w+)\]/);
                            if (!match) return;

                            numeric.push({ key: match[1].charAt(0), isCount: select.value === 'count' });
                        });

                        const tokens = [];
                        numeric.forEach((m) => {
                            tokens.push('{' + m.key + '}');
                            if (m.isCount) {
                                tokens.push('{' + m.key + '_ratio}', '{' + m.key + '_count}', '{' + m.key + '_total}');
                            }
                        });

                        if (numeric.length === 1) {
                            tokens.push('{value}');
                            if (numeric[0].isCount) tokens.push('{ratio}');
                        }

                        this.placeholders = tokens;
                    },
                };
            }
        </script>
    @endpush
@endonce

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
{{-- The template is seeded through the argument rather than left in the
     textarea: x-model would blank it on the first tick otherwise. --}}
<div class="space-y-3"
    x-data="functionRubric(@js(old('accomplishment_template', $function?->accomplishment_template ?? '')))">
    <div class="flex items-baseline justify-between gap-3">
        <h3 class="text-sm font-semibold text-gray-900">Description of Rating</h3>
        <span class="text-xs text-gray-500">Blank = n/a</span>
    </div>

    @foreach (RatingMeasure::cases() as $measure)
        @php
            $rubric = $saved->get($measure->value);
            $bands = $rubric?->bands->keyBy('level') ?? collect();
            $answer = old("rubric.{$measure->value}.answer", $rubric?->answer?->value ?? MeasureAnswer::Descriptor->value);
            $unit = old("rubric.{$measure->value}.unit", $rubric?->unit ?? '%');

            // No levels behind a saved measure means it was never graded: the
            // figure is printed and nothing marks it.
            $reportedOnly = (bool) old(
                "rubric.{$measure->value}.reported_only",
                $rubric && $rubric->bands->isEmpty(),
            );
        @endphp

        <details class="rounded-lg ring-1 ring-inset ring-gray-200" @if ($rubric) open @endif
            x-data="{ answer: '{{ $answer }}', reportedOnly: {{ $reportedOnly ? 'true' : 'false' }} }"
            x-bind:data-numeric="answer !== 'descriptor'">
            <summary class="flex cursor-pointer items-baseline justify-between gap-3 px-4 py-2.5 text-sm hover:bg-gray-50">
                <span class="font-medium text-gray-800">
                    {{ $measure->label() }} <span class="font-data text-xs text-gray-400">({{ strtoupper($measure->key()) }})</span>
                </span>
                <span class="text-xs text-gray-400">
                    @if (!$rubric)
                        n/a — click to use
                    @elseif ($reportedOnly)
                        reported, not rated
                    @else
                        rated
                    @endif
                </span>
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

                    <span class="text-xs italic text-gray-400" x-show="answer === 'count' && !reportedOnly" x-cloak>
                        bands below are in %
                    </span>
                </div>

                {{-- A figure that is printed and never graded. Real wordings
                     carry one all the time - "100% of reports within 12 days"
                     is one sentence with two figures and one mark - and the
                     only way to name the other in the wording used to be to
                     invent five levels for it and then ignore them. --}}
                <label class="flex items-start gap-2 rounded-md bg-gray-50 p-2.5 ring-1 ring-inset ring-gray-200">
                    <input type="checkbox" name="rubric[{{ $measure->value }}][reported_only]" value="1"
                        x-model="reportedOnly" class="mt-0.5 rounded border-gray-300 text-nav-900 focus:ring-seal">
                    <span class="text-xs text-gray-600">
                        <span class="font-medium text-gray-800">Report this figure without rating it.</span>
                        The employee still types it and it still goes into the wording, but it earns no mark and
                        stays out of the average.
                    </span>
                </label>

                <div class="overflow-x-auto" x-show="!reportedOnly" x-cloak>

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

                {{-- The two rules that are not guessable: an open end, and the
                     order bands are read in. Everything else the grid shows. --}}
                <p class="text-xs text-gray-500" x-show="answer !== 'descriptor'" x-cloak>
                    Blank From = anything below To. Read from 5 down, so a “fewer is better” scale is written
                    upside down.
                </p>
            </div>
        </details>
    @endforeach

    {{-- Only a reported figure can fill a sentence, so this appears only once
         a measure is answered by a number. --}}
    <div class="rounded-lg bg-gray-50 p-4 ring-1 ring-inset ring-gray-200" x-show="placeholders.length" x-cloak>
        <label class="block">
            <span class="mb-1 block text-sm font-medium text-gray-700">Accomplishment wording</span>
            <textarea name="accomplishment_template" rows="2" x-model="template"
                class="w-full rounded-md border-gray-300 text-sm"
                placeholder="{e}% of DTR with complete attachments are submitted every 5th day of the ensuing month"></textarea>
        </label>

        <p class="mt-1 text-xs text-gray-500">
            Use <span class="font-data text-gray-700" x-text="placeholders.join('  ')"></span>
            where the reported figure goes.
        </p>

        {{-- Said here rather than on save. The panels above are what decides
             which placeholders exist, and the answer to "why can I not use
             {e}?" is always a setting a few inches up the page. --}}
        <template x-if="unusable.length">
            <div class="mt-2 rounded-md bg-amber-50 p-2.5 text-xs text-amber-900 ring-1 ring-inset ring-amber-500/30">
                <span class="font-data font-medium" x-text="unusable.join(' ')"></span>
                would be printed as written.
                <template x-for="reason in reasons" :key="reason">
                    <span x-text="reason + ' '"></span>
                </template>
            </div>
        </template>
    </div>
</div>

@once
    @push('scripts')
        <script>
            // Mirrors JobFunction::placeholders(). The list follows the panels
            // rather than the saved rubric, so it is right while still typing.
            function functionRubric(template = '') {
                return {
                    placeholders: [],
                    numeric: [],
                    template: template ?? '',

                    names: { q: 'Quality', e: 'Efficiency', t: 'Timeliness' },

                    init() {
                        this.refresh();
                        this.$el.addEventListener('change', () => this.refresh());
                    },

                    /** Placeholders typed into the wording that nothing can fill. */
                    get unusable() {
                        const used = this.template.match(/\{[^}]*\}/g) ?? [];

                        return [...new Set(used)].filter((t) => !this.placeholders.includes(t));
                    },

                    /** Why, in terms of the panel to go and change. */
                    get reasons() {
                        const said = new Set();

                        this.unusable.forEach((token) => {
                            const [head, suffix] = token.slice(1, -1).split('_');
                            const name = this.names[head];
                            if (!name) return;

                            const measure = this.numeric.find((m) => m.key === head);

                            if (!measure) {
                                said.add(name + ' is not graded on a figure yet.');
                            } else if (['ratio', 'count', 'total'].includes(suffix)) {
                                said.add(name + ' is not answered by counting out of a total.');
                            } else if (suffix === 'when') {
                                said.add(name + ' is not answered by a number in days.');
                            }
                        });

                        return [...said];
                    },

                    refresh() {
                        const numeric = [];

                        this.$el.querySelectorAll('details').forEach((panel) => {
                            const select = panel.querySelector('select[name$="[answer]"]');
                            if (!select || select.value === 'descriptor') return;

                            const match = select.name.match(/rubric\[(\w+)\]/);
                            if (!match) return;

                            // A scale in days runs either side of a deadline,
                            // which is what gives it a before and an after.
                            const unit = panel.querySelector('select[name$="[unit]"]');

                            numeric.push({
                                key: match[1].charAt(0),
                                isCount: select.value === 'count',
                                inDays: select.value === 'number' && unit?.value === 'days',
                            });
                        });

                        const tokens = [];
                        numeric.forEach((m) => {
                            tokens.push('{' + m.key + '}');
                            if (m.isCount) {
                                tokens.push('{' + m.key + '_ratio}', '{' + m.key + '_count}', '{' + m.key + '_total}');
                            }
                            if (m.inDays) tokens.push('{' + m.key + '_when}');
                        });

                        if (numeric.length === 1) {
                            tokens.push('{value}');
                            if (numeric[0].isCount) tokens.push('{ratio}');
                            if (numeric[0].inDays) tokens.push('{when}');
                        }

                        this.numeric = numeric;
                        this.placeholders = tokens;
                    },
                };
            }
        </script>
    @endpush
@endonce

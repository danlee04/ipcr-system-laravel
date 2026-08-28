<?php

namespace App\Http\Requests\Admin;

use App\Enums\FunctionCategory;
use App\Enums\MeasureAnswer;
use App\Enums\RatingMeasure;
use App\Models\FunctionMeasure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The rules a catalog function is saved under, shared by create and edit so
 * the two cannot drift apart.
 *
 * A function answers two separate questions, and they used to be tangled:
 *
 *   category   -> what kind of work it is: strategic, core, support, or the
 *                 common pool
 *   applies to -> who it reaches: whoever holds a position, or whoever holds
 *                 a designation
 *
 * The category never decided the audience. A Section Head's strategic
 * commitments belong to their post; an OIC's core duties belong to their
 * designation. So every rated category takes either link - exactly one - and
 * common takes neither, reaching everyone.
 */
trait LinksAFunction
{
    private function category(): ?FunctionCategory
    {
        return FunctionCategory::tryFrom((string) $this->input('category'));
    }

    private function functionRules(): array
    {
        $category = $this->category();

        return [
            'category' => ['required', Rule::enum(FunctionCategory::class)],

            // Neither link is required here. Which one is needed depends on
            // the other, so the requirement is checked in one place below,
            // where a single clear message can be given.
            'position_id'    => ['nullable', 'integer', 'exists:positions,id'],
            'designation_id' => ['nullable', 'integer', 'exists:designations,id'],

            /*
             * Who the function reaches. Required, and asked as plainly as the
             * category is.
             *
             * Not stored as a column - the link it carries is the answer - but
             * the form has to say which branch it meant, because the inactive
             * branches submit too. Left to be inferred, a request naming no
             * link at all would quietly create a function for the whole
             * hospital, which is the last thing anyone should get by accident.
             */
            'applies_to' => ['required', Rule::in(['everyone', 'position', 'designation'])],

            'title'             => ['required', 'string', 'max:2000'],
            'success_indicator' => ['nullable', 'string', 'max:2000'],

            // The rubric and the sentence it writes. Both optional: functions
            // predate rubrics, and one that has none is marked by hand as
            // before.
            'accomplishment_template' => ['nullable', 'string', 'max:2000'],
            'rubric'                  => ['nullable', 'array'],
            'rubric.*.answer'         => ['nullable', Rule::in(MeasureAnswer::values())],
            'rubric.*.reported_only'  => ['nullable', 'boolean'],
            'rubric.*.unit'           => ['nullable', Rule::in(FunctionMeasure::UNITS)],

            /*
             * The five levels need rules of their own, not because much can be
             * said about them here - the real checks are in validateTheRubric,
             * where one message can name the measure and the level - but
             * because validated() returns only what was validated. Without
             * these the levels are stripped on the way to the controller and
             * every rubric saves as empty, silently.
             */
            'rubric.*.*.description' => ['nullable', 'string', 'max:500'],
            'rubric.*.*.min'         => ['nullable', 'numeric'],
            'rubric.*.*.max'         => ['nullable', 'numeric'],
        ];
    }

    /**
     * A measure is all or nothing - unless it is not being graded at all.
     *
     * Left blank it is n/a, which is allowed and common. Reported only, it
     * carries a figure into the wording and earns no mark, so it has no levels
     * to be missing. Otherwise all five have to be there: a scale with a hole
     * in it would silently refuse to grade whatever falls in the gap.
     */
    private function validateTheRubric(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $rubric = $this->input('rubric', []);

            if (! is_array($rubric)) {
                return;
            }

            foreach (RatingMeasure::cases() as $measure) {
                $this->validateMeasure($validator, $measure, $rubric[$measure->value] ?? []);
            }

            $this->somethingMustBeGraded($validator);
            $this->templateMustNameAFigure($validator);
        });
    }

    private function validateMeasure(Validator $validator, RatingMeasure $measure, mixed $input): void
    {
        if (! is_array($input)) {
            return;
        }

        // Reported only: the figure appears in the wording and nothing
        // grades it, so there are no levels to insist on.
        if (filter_var($input['reported_only'] ?? false, FILTER_VALIDATE_BOOL)) {
            return;
        }

        $answer = MeasureAnswer::tryFrom((string) ($input['answer'] ?? '')) ?? MeasureAnswer::Descriptor;
        $numeric = $answer->isNumeric();
        $field = "rubric.{$measure->value}";

        $started = false;

        foreach (FunctionMeasure::LEVELS as $level) {
            $row = is_array($input[$level] ?? null) ? $input[$level] : [];

            if (trim((string) ($row['description'] ?? '')) !== ''
                || ($numeric && ($row['min'] ?? '') !== '')
                || ($numeric && ($row['max'] ?? '') !== '')) {
                $started = true;
            }
        }

        if (! $started) {
            return;   // n/a, and that is allowed
        }

        foreach (FunctionMeasure::LEVELS as $level) {
            $row = is_array($input[$level] ?? null) ? $input[$level] : [];

            if (trim((string) ($row['description'] ?? '')) === '') {
                $validator->errors()->add(
                    "{$field}.{$level}.description",
                    "{$measure->label()} level {$level} needs a description - a rated measure needs all five."
                );
            }

            if (! $numeric) {
                continue;
            }

            $min = ($row['min'] ?? '') === '' ? null : (float) $row['min'];
            $max = ($row['max'] ?? '') === '' ? null : (float) $row['max'];

            if ($min === null && $max === null) {
                $validator->errors()->add(
                    "{$field}.{$level}.min",
                    "{$measure->label()} level {$level} needs a From or a To, or nothing can grade it."
                );
            } elseif ($min !== null && $max !== null && $min > $max) {
                $validator->errors()->add("{$field}.{$level}.min", "{$measure->label()} level {$level}: From is larger than To.");
            }
        }
    }

    /**
     * A rubric that grades nothing is not a rubric.
     *
     * Reported-only measures print a figure and earn no mark. If every measure
     * is one of those, the line can never be rated: the rubric will not give a
     * mark and the employee is not offered the box for a measure the catalog
     * has claimed. It could then be neither finished nor submitted.
     */
    private function somethingMustBeGraded(Validator $validator): void
    {
        $rubric = (array) $this->input('rubric', []);

        $used = collect(RatingMeasure::cases())
            ->map(fn (RatingMeasure $m): array => (array) ($rubric[$m->value] ?? []))
            ->filter(fn (array $input): bool => $this->measureIsUsed($input));

        if ($used->isEmpty()) {
            return;   // no rubric at all, which is allowed
        }

        $graded = $used->reject(fn (array $input): bool => filter_var(
            $input['reported_only'] ?? false,
            FILTER_VALIDATE_BOOL
        ));

        if ($graded->isEmpty()) {
            $validator->errors()->add(
                'rubric',
                'At least one measure has to be graded. Reported-only measures print a figure and earn no mark, '
                    . 'so a rubric made only of those leaves the line with no rating at all.'
            );
        }
    }

    /** Has anything been said about this measure at all? */
    private function measureIsUsed(array $input): bool
    {
        if (filter_var($input['reported_only'] ?? false, FILTER_VALIDATE_BOOL)) {
            return true;
        }

        foreach (FunctionMeasure::LEVELS as $level) {
            $row = is_array($input[$level] ?? null) ? $input[$level] : [];

            if (trim((string) ($row['description'] ?? '')) !== ''
                || ($row['min'] ?? '') !== ''
                || ($row['max'] ?? '') !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Every placeholder in the template has to be one this rubric can fill,
     * and there has to be at least one.
     *
     * Both halves matter. A template with no placeholder reads the same every
     * period, which is worse than no template - it looks filled in. And a
     * placeholder nothing fills is left exactly as typed, so the sentence on
     * somebody's IPCR ends up carrying "{t_ratio}" in the middle of it. That
     * is the one that catches people out: a ratio exists only where the
     * measure is counted, and a typed number has no ratio to give.
     */
    private function templateMustNameAFigure(Validator $validator): void
    {
        $template = trim((string) $this->input('accomplishment_template'));

        if ($template === '') {
            return;
        }

        $allowed = $this->fillablePlaceholders();

        // A brace that never closes, or closes with the wrong bracket. Caught
        // by name, because "there is no placeholder here" is a baffling thing
        // to be told about a line you can plainly see one on.
        if (preg_match('/\{[^{}]*(?:$|[^}])(?=[^{}]*(?:\{|$))/', $template, $broken)) {
            $validator->errors()->add('accomplishment_template', sprintf(
                '"%s" is missing its closing brace. A placeholder is written {t}, with braces at both ends.',
                trim(mb_substr($broken[0], 0, 20)),
            ));

            return;
        }

        preg_match_all('/\{[^}]*\}/', $template, $found);
        $used = array_unique($found[0]);

        if ($used === []) {
            $validator->errors()->add(
                'accomplishment_template',
                'Put a placeholder where the measured figure goes, or the sentence reads the same every period.'
            );

            return;
        }

        $unknown = array_diff($used, $allowed);

        if ($unknown === []) {
            return;
        }

        // Saying what is allowed is not the same as saying how to get what was
        // wanted. Somebody who typed {t_when} wants the days reading, and the
        // useful reply is which setting gives it to them - not a list they
        // then have to work backwards from.
        $reasons = collect($unknown)
            ->map(fn (string $token): ?string => $this->whyUnusable($token))
            ->filter()
            ->unique()
            ->implode(' ');

        $validator->errors()->add('accomplishment_template', trim(sprintf(
            '%s %s nothing this rubric can fill, so it would be printed as written. %s Available: %s',
            implode(' and ', $unknown),
            count($unknown) === 1 ? 'names' : 'name',
            $reasons,
            $allowed === [] ? 'none until a measure is graded on a figure' : implode(', ', $allowed),
        )));
    }

    /**
     * Why one placeholder cannot be filled, in terms of the setting to change.
     *
     * Null when nothing specific can be said - a plain typo, or one of the
     * short forms - and the list of what is available says it better.
     */
    private function whyUnusable(string $token): ?string
    {
        $rubric = $this->input('rubric', []);

        [$head, $suffix] = array_pad(explode('_', trim($token, '{}'), 2), 2, null);

        $measure = collect(RatingMeasure::cases())
            ->first(fn (RatingMeasure $m): bool => $m->key() === $head);

        if ($measure === null) {
            return null;
        }

        $answer = MeasureAnswer::tryFrom((string) ($rubric[$measure->value]['answer'] ?? ''));

        if (! $answer?->isNumeric()) {
            return "{$measure->label()} is not graded on a figure yet - answer it by a number or a count first.";
        }

        return match ($suffix) {
            'ratio', 'count', 'total' => "Only a counted measure has parts to name, and {$measure->label()} is answered by a typed number.",
            'when' => "A before and an after need days, and {$measure->label()} is not answered by a number in days.",
            default => null,
        };
    }

    /**
     * The placeholders the rubric being saved can actually supply.
     *
     * Kept in step with AccomplishmentWriter::render(), which is what does the
     * filling - a token offered here and not filled there is the bug this
     * exists to prevent.
     *
     * @return list<string>
     */
    private function fillablePlaceholders(): array
    {
        $rubric = $this->input('rubric', []);
        $tokens = [];
        $numeric = [];

        foreach (RatingMeasure::cases() as $measure) {
            $answer = MeasureAnswer::tryFrom((string) ($rubric[$measure->value]['answer'] ?? ''));

            if (! $answer?->isNumeric()) {
                continue;
            }

            $inDays = $answer === MeasureAnswer::Number
                && ($rubric[$measure->value]['unit'] ?? null) === 'days';

            $numeric[] = ['answer' => $answer, 'inDays' => $inDays];
            $key = $measure->key();
            $tokens[] = "{{$key}}";

            // Only a counted measure has parts to name. A typed number is one
            // figure and nothing divides it.
            if ($answer === MeasureAnswer::Count) {
                $tokens = array_merge($tokens, ["{{$key}_ratio}", "{{$key}_count}", "{{$key}_total}"]);
            }

            // And only a scale in days has a before and an after.
            if ($inDays) {
                $tokens[] = "{{$key}_when}";
            }
        }

        // With exactly one figure in the whole rubric, naming which one is
        // ceremony - there is only one it could mean.
        if (count($numeric) === 1) {
            $tokens[] = '{value}';

            if ($numeric[0]['answer'] === MeasureAnswer::Count) {
                $tokens[] = '{ratio}';
            }

            if ($numeric[0]['inDays']) {
                $tokens[] = '{when}';
            }
        }

        return $tokens;
    }

    /**
     * Exactly one link, and the right kind for the category.
     *
     * Done after the field rules rather than inside them so the message can
     * say what is actually wrong, instead of "the position field is required"
     * on a form where a designation would have done just as well.
     */
    private function validateTheLink(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['category', 'position_id', 'designation_id'])) {
                return;
            }

            // The branch the form was on decides what is required. Everyone
            // needs nothing; the other two each need their own link, and
            // whatever the inactive branches submitted is discarded later.
            match ($this->appliesTo()) {
                'position' => $this->input('position_id')
                    ?: $validator->errors()->add('position_id', 'Choose the position this function belongs to.'),

                'designation' => $this->input('designation_id')
                    ?: $validator->errors()->add('designation_id', 'Choose the designation this function belongs to.'),

                default => null,   // Everyone: no link to choose.
            };
        });
    }

    /** Which of the three routes the form was on. */
    private function appliesTo(): string
    {
        return (string) $this->input('applies_to');
    }

    private function functionMessages(): array
    {
        return [
            'applies_to.required' => 'Say who this function reaches: everyone, a position, or a designation.',
        ];
    }
}

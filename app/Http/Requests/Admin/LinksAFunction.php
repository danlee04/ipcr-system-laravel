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
     * A measure is all or nothing.
     *
     * Left blank it is n/a, which is allowed and common. Once anything is
     * typed into it, all five levels have to be there - a scale with a hole in
     * it would silently refuse to grade whatever falls in the gap.
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

            $this->templateMustNameAFigure($validator);
        });
    }

    private function validateMeasure(Validator $validator, RatingMeasure $measure, mixed $input): void
    {
        if (! is_array($input)) {
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
     * A template with no placeholder would read the same every period, which
     * is worse than no template - it looks filled in.
     */
    private function templateMustNameAFigure(Validator $validator): void
    {
        $template = trim((string) $this->input('accomplishment_template'));

        if ($template === '') {
            return;
        }

        $rubric = $this->input('rubric', []);
        $tokens = ['{value}', '{ratio}'];

        foreach (RatingMeasure::cases() as $measure) {
            $answer = MeasureAnswer::tryFrom((string) ($rubric[$measure->value]['answer'] ?? ''));

            if ($answer?->isNumeric()) {
                $key = $measure->key();
                $tokens = array_merge($tokens, ["{{$key}}", "{{$key}_ratio}", "{{$key}_count}", "{{$key}_total}"]);
            }
        }

        foreach ($tokens as $token) {
            if (str_contains($template, $token)) {
                return;
            }
        }

        $validator->errors()->add(
            'accomplishment_template',
            'Put a placeholder where the measured figure goes, or the sentence reads the same every period.'
        );
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

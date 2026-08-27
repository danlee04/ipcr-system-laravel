<?php

namespace App\Services;

use App\Enums\MeasureAnswer;
use App\Enums\RatingMeasure;
use App\Models\FunctionMeasure;
use App\Models\JobFunction;
use Illuminate\Support\Facades\DB;

/**
 * Writes the rubric typed on the function form onto the function.
 *
 * A measure with nothing filled in is n/a and its rows are removed - that is
 * how a rubric is taken back off a function, and it has to be possible or a
 * measure added by mistake could never be undone.
 */
class RubricSync
{
    /**
     * @param  array<string, array<string, mixed>>  $rubric  keyed by measure
     */
    public function apply(JobFunction $function, array $rubric): void
    {
        DB::transaction(function () use ($function, $rubric): void {
            foreach (RatingMeasure::cases() as $measure) {
                $input = $rubric[$measure->value] ?? [];

                $this->applyMeasure($function, $measure, is_array($input) ? $input : []);
            }
        });
    }

    /** @param array<string, mixed> $input */
    private function applyMeasure(JobFunction $function, RatingMeasure $measure, array $input): void
    {
        $answer = MeasureAnswer::tryFrom((string) ($input['answer'] ?? '')) ?? MeasureAnswer::Descriptor;
        $bands = $this->bandsFrom($input, $answer);

        $existing = $function->measures()->where('measure', $measure->value)->first();

        if ($bands === []) {
            // Nothing typed: the measure does not apply to this function.
            $existing?->delete();

            return;
        }

        $rubric = $existing ?? new FunctionMeasure(['job_function_id' => $function->id]);

        $rubric->fill([
            'measure' => $measure->value,
            'answer'  => $answer->value,
            // A count is a percentage by construction, so it names no unit.
            'unit'    => $answer->hasUnit() ? ($input['unit'] ?? null) : null,
        ])->save();

        // Replaced rather than merged: the five levels are one statement, and
        // a level dropped from the form has to disappear from the rubric too.
        $rubric->bands()->delete();
        $rubric->bands()->createMany($bands);
    }

    /**
     * The five levels, or nothing at all when the measure was left blank.
     *
     * @param  array<string, mixed>  $input
     * @return list<array{level: int, description: string, min_value: ?float, max_value: ?float}>
     */
    private function bandsFrom(array $input, MeasureAnswer $answer): array
    {
        $bands = [];
        $anything = false;

        foreach (FunctionMeasure::LEVELS as $level) {
            $row = is_array($input[$level] ?? null) ? $input[$level] : [];

            $description = trim((string) ($row['description'] ?? ''));
            $min = $answer->isNumeric() ? $this->number($row['min'] ?? null) : null;
            $max = $answer->isNumeric() ? $this->number($row['max'] ?? null) : null;

            if ($description !== '' || $min !== null || $max !== null) {
                $anything = true;
            }

            $bands[] = [
                'level'       => $level,
                'description' => $description,
                'min_value'   => $min,
                'max_value'   => $max,
            ];
        }

        return $anything ? $bands : [];
    }

    private function number(mixed $raw): ?float
    {
        return ($raw === null || $raw === '' || ! is_numeric($raw)) ? null : (float) $raw;
    }
}

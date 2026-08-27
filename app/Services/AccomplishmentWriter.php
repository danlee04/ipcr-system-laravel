<?php

namespace App\Services;

use App\Enums\MeasureAnswer;
use App\Models\IpcrItem;
use App\Models\IpcrItemMeasure;
use App\Models\JobFunction;

/**
 * Turns the figures an employee reports into the two things the form needs:
 * the sentence that says what they accomplished, and the mark each measure
 * earns.
 *
 * Both come from the rubric on the catalog function, so the same performance
 * is written and graded the same way whoever reports it. A function with no
 * rubric is untouched - the assessor marks it by hand as before.
 */
class AccomplishmentWriter
{
    /**
     * Write the accomplishment and the marks onto one line.
     *
     * @param  array<string, array{value?: mixed, count?: mixed, total?: mixed}>  $reported
     *         keyed by measure: quality, efficiency, timeliness
     */
    public function apply(IpcrItem $item, JobFunction $function, array $reported): void
    {
        $function->loadMissing('measures.bands');

        $stored = [];

        foreach ($function->measures as $measure) {
            $key = $measure->measure->value;
            $figures = $this->figuresFor($measure->answer, $reported[$key] ?? []);

            if ($figures === null) {
                // Nothing reported for this measure: leave it n/a rather than
                // marking it zero, which is a real mark and a bad one.
                $item->{$measure->measure->column()} = null;
                IpcrItemMeasure::query()
                    ->where('ipcr_item_id', $item->id)
                    ->where('measure', $key)
                    ->delete();

                continue;
            }

            $stored[$key] = $figures;

            IpcrItemMeasure::updateOrCreate(
                ['ipcr_item_id' => $item->id, 'measure' => $key],
                [
                    'value'          => $figures['value'],
                    'reported_count' => $figures['count'],
                    'reported_total' => $figures['total'],
                ]
            );

            $item->{$measure->measure->column()} = $measure->answer->isNumeric()
                ? $measure->levelFor($figures['value'])
                : $figures['value'];   // a descriptor answer IS the level
        }

        $item->actual_accomplishment = $this->render($function, $stored)
            ?? $item->actual_accomplishment;

        $item->save();
    }

    /**
     * The sentence, with every placeholder replaced.
     *
     * Null when there is no template to fill, so the caller keeps whatever the
     * employee wrote themselves rather than blanking it.
     *
     * @param  array<string, array{value: float, count: ?float, total: ?float}>  $stored
     */
    public function render(JobFunction $function, array $stored): ?string
    {
        $template = trim((string) $function->accomplishment_template);

        if ($template === '' || $stored === []) {
            return null;
        }

        $numeric = $function->numericMeasures();
        $replacements = [];

        foreach ($numeric as $measure) {
            $key = $measure->measure->key();
            $figures = $stored[$measure->measure->value] ?? null;

            if ($figures === null) {
                continue;
            }

            $replacements['{' . $key . '}'] = $this->tidy($figures['value']);

            if ($measure->answer === MeasureAnswer::Count) {
                $replacements['{' . $key . '_count}'] = $this->tidy($figures['count']);
                $replacements['{' . $key . '_total}'] = $this->tidy($figures['total']);
                $replacements['{' . $key . '_ratio}'] = $this->tidy($figures['count'])
                    . ' of ' . $this->tidy($figures['total']);
            }
        }

        // With one numeric measure the unqualified form means that one.
        if ($numeric->count() === 1) {
            $only = $numeric->first();
            $key = $only->measure->key();

            $replacements['{value}'] = $replacements['{' . $key . '}'] ?? '';

            if ($only->answer === MeasureAnswer::Count) {
                $replacements['{ratio}'] = $replacements['{' . $key . '_ratio}'] ?? '';
            }
        }

        return $replacements === []
            ? null
            : strtr($template, $replacements);
    }

    /**
     * The figure a measure was answered with, or null when it was left out.
     *
     * A count is divided here rather than stored twice over: the bands are
     * read against the percentage, and the two parts are kept only so the
     * sentence can say "12 of 12".
     *
     * @param  array{value?: mixed, count?: mixed, total?: mixed}  $input
     * @return array{value: float, count: ?float, total: ?float}|null
     */
    private function figuresFor(MeasureAnswer $answer, array $input): ?array
    {
        if ($answer === MeasureAnswer::Count) {
            $count = $this->number($input['count'] ?? null);
            $total = $this->number($input['total'] ?? null);

            // Nothing to divide by: not an error, just nothing reported.
            if ($count === null || $total === null || $total <= 0.0) {
                return null;
            }

            return [
                'value' => round($count / $total * 100, 2),
                'count' => $count,
                'total' => $total,
            ];
        }

        $value = $this->number($input['value'] ?? null);

        return $value === null ? null : ['value' => $value, 'count' => null, 'total' => null];
    }

    private function number(mixed $raw): ?float
    {
        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return null;
        }

        return (float) $raw;
    }

    /** 100 rather than 100.00, but 99.5 kept as 99.5. */
    private function tidy(?float $value): string
    {
        if ($value === null) {
            return '';
        }

        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}

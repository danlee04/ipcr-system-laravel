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
     * The measures whose reported figure falls in no level at all.
     *
     * Asked before anything is written. A figure nothing accepts would be
     * stored with no mark against it, and the gap would surface only much
     * later - when the assessor cannot complete the assessment and nothing on
     * the screen says why.
     *
     * @param  array<string, array{value?: mixed, count?: mixed, total?: mixed}>  $reported
     * @return array<string, string> measure name => why it was refused
     */
    public function ungradable(JobFunction $function, array $reported): array
    {
        $function->loadMissing('measures.bands');

        $refused = [];

        foreach ($function->measures as $measure) {
            $figures = $this->figuresFor($measure->answer, $reported[$measure->measure->value] ?? []);

            if ($figures === null) {
                continue;   // nothing reported is n/a, not an error
            }

            $name = $measure->measure->label();

            // Checked before the bands, and checked on a reported-only measure
            // too - that one has no bands at all, so this is the only thing
            // standing between a slip of the keyboard and "-5%" on the sheet.
            if ($figures['value'] < 0 && ! $measure->acceptsNegative()) {
                $refused[$name] = "{$name} cannot be below zero.";

                continue;
            }

            if ($measure->bands->isEmpty()) {
                continue;   // reported only: printed, never graded
            }

            // A descriptor is picked, not computed, so what has to hold is
            // that the pick is one of the levels actually written down.
            $graded = $measure->answer->isNumeric()
                ? $measure->levelFor($figures['value']) !== null
                : $measure->bands->contains('level', (int) $figures['value']);

            if (! $graded) {
                $refused[$name] = "The figure you reported for {$name} falls outside every level of this "
                    . 'function\'s rubric. Check it against the levels shown beside the field.';
            }
        }

        return $refused;
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
                // A slash, not the word: these sit in brackets beside the
                // percentage - "100% (7/7)" - where "7 of 7" reads as prose in
                // the middle of a figure.
                $replacements['{' . $key . '_ratio}'] = $this->tidy($figures['count'])
                    . '/' . $this->tidy($figures['total']);
            }

            if ($measure->readsAsDaysFromDeadline()) {
                $replacements['{' . $key . '_when}'] = $this->daysFromDeadline($figures['value']);
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

            if ($only->readsAsDaysFromDeadline()) {
                $replacements['{when}'] = $replacements['{' . $key . '_when}'] ?? '';
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
     * sentence can say "12/12".
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

    /**
     * A signed number of days, said the way a person would say it.
     *
     * The scale is written from the deadline, so the sign is the whole point:
     * -5 is five days early and 5 is five days late. The template author gets
     * the sign, the plural and the on-time case for free, because getting any
     * of the three wrong makes the sheet read badly and none of them is their
     * problem to solve.
     */
    private function daysFromDeadline(float $days): string
    {
        if ($days === 0.0) {
            return 'on time';
        }

        $count = $this->tidy(abs($days));
        $noun = abs($days) === 1.0 ? 'day' : 'days';

        return $days < 0
            ? "{$count} {$noun} before the deadline"
            : "{$count} {$noun} after the deadline";
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

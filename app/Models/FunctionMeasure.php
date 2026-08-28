<?php

namespace App\Models;

use App\Enums\MeasureAnswer;
use App\Enums\RatingMeasure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * How one function is graded on one measure.
 *
 * A function with no row for a measure is not rated on it. That is not an
 * omission - most outputs have no Timeliness dimension - and it is why the
 * rating averages the marks that apply rather than always three.
 */
class FunctionMeasure extends Model
{
    /**
     * The units a typed figure can be in.
     *
     * A starting set, not a standard: add to it as DTRC needs. A counted
     * measure never appears here - it is a percentage by construction.
     */
    public const UNITS = ['%', 'days', 'hours', 'number', 'pesos'];

    /** Levels are tried from the top: the highest band that accepts wins. */
    public const LEVELS = [5, 4, 3, 2, 1];

    protected $fillable = ['job_function_id', 'measure', 'answer', 'unit'];

    protected function casts(): array
    {
        return [
            'measure' => RatingMeasure::class,
            'answer'  => MeasureAnswer::class,
        ];
    }

    public function jobFunction(): BelongsTo
    {
        return $this->belongsTo(JobFunction::class);
    }

    public function bands(): HasMany
    {
        return $this->hasMany(FunctionRatingBand::class)->orderByDesc('level');
    }

    /**
     * Does a figure below zero mean anything here?
     *
     * Only where the scale itself is written across zero: a timeliness ladder
     * counted from the deadline, where minus five is five days early and earns
     * the top mark. The bands say so themselves - a bound below zero is the
     * only reason to write one.
     *
     * Everywhere else a negative is a typo. A percentage ladder is open at the
     * bottom so that anything under seventy scores a one, and minus five went
     * through the same door; a reported-only figure has no ladder at all and
     * nothing was checking it.
     */
    public function acceptsNegative(): bool
    {
        return $this->bands->contains(fn (FunctionRatingBand $band): bool => ($band->min_value !== null && (float) $band->min_value < 0)
            || ($band->max_value !== null && (float) $band->max_value < 0));
    }

    /**
     * Is a mark worked out from this figure, or is it only printed?
     *
     * No levels means nothing to grade against. Plenty of wordings carry a
     * number nobody rates - "100% of reports within 12 days" is one sentence
     * with two figures and one mark - and inventing five levels for the other
     * one only to ignore them would be a lie in the catalog.
     */
    public function isGraded(): bool
    {
        return $this->bands->isNotEmpty();
    }

    /**
     * The mark this figure earns, or null when nothing accepts it.
     *
     * Tried from 5 down so the highest band that accepts the figure wins,
     * which is what lets a timeliness scale be written the other way round -
     * level 5 up to 90 days, level 1 from 181 onwards.
     */
    public function levelFor(float $value): ?int
    {
        foreach ($this->bands->sortByDesc('level') as $band) {
            if ($band->contains($value)) {
                return $band->level;
            }
        }

        return null;
    }

    /**
     * The placeholders this measure offers a template.
     *
     * A counted measure offers the parts as well as the percentage, because
     * the sentence usually wants to say "(12/12)" beside the percentage.
     *
     * A measure in days offers the reading in words. Those scales are written
     * from the deadline - minus is early, plus is late - and nobody wants "-5"
     * sitting in the middle of a sentence.
     *
     * @return list<string>
     */
    public function placeholders(): array
    {
        if (! $this->answer->isNumeric()) {
            return [];
        }

        $key = $this->measure->key();
        $tokens = ['{' . $key . '}'];

        if ($this->answer === MeasureAnswer::Count) {
            $tokens[] = '{' . $key . '_ratio}';
            $tokens[] = '{' . $key . '_count}';
            $tokens[] = '{' . $key . '_total}';
        }

        if ($this->readsAsDaysFromDeadline()) {
            $tokens[] = '{' . $key . '_when}';
        }

        return $tokens;
    }

    /**
     * Is this figure a number of days either side of a deadline?
     *
     * Only then does "before" or "after" mean anything. On a percentage it
     * would read "5 % before the deadline", so it is not offered at all.
     */
    public function readsAsDaysFromDeadline(): bool
    {
        return $this->answer === MeasureAnswer::Number && $this->unit === 'days';
    }
}

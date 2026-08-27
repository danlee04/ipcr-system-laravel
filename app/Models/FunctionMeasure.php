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
     * the sentence usually wants to say "12 of 12" rather than "100%".
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

        return $tokens;
    }
}

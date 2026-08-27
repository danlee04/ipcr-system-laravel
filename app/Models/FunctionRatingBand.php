<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One of the five levels of one measure.
 *
 * A numeric band carries the range that earns it. Either end may be open, and
 * a band with both ends open catches everything - which is how the bottom
 * level is usually written.
 */
class FunctionRatingBand extends Model
{
    protected $fillable = ['function_measure_id', 'level', 'description', 'min_value', 'max_value'];

    protected function casts(): array
    {
        return [
            'level'     => 'integer',
            'min_value' => 'decimal:2',
            'max_value' => 'decimal:2',
        ];
    }

    public function measure(): BelongsTo
    {
        return $this->belongsTo(FunctionMeasure::class, 'function_measure_id');
    }

    /**
     * Does this figure earn this level?
     *
     * An open end is not a missing bound, it is "no limit this way": a band
     * with a blank From accepts anything up to its To. Bands are tried from 5
     * down, so the highest one that accepts the figure is the one it earns.
     */
    public function contains(float $value): bool
    {
        if ($this->min_value !== null && $value < (float) $this->min_value) {
            return false;
        }

        if ($this->max_value !== null && $value > (float) $this->max_value) {
            return false;
        }

        return true;
    }
}

<?php

namespace App\Models;

use App\Enums\FunctionCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IpcrItem extends Model
{
    use HasFactory;

    protected $table = 'ipcr_items';

    protected $fillable = [
        'ipcr_id',
        'job_function_id',
        'category',
        'output',
        'success_indicator',
        'weight',
        'actual_accomplishment',
        'quality_rating',
        'efficiency_rating',
        'timeliness_rating',
        'average_rating',
        'remarks',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'category'          => FunctionCategory::class,
            'weight'            => 'decimal:2',
            'quality_rating'    => 'decimal:2',
            'efficiency_rating' => 'decimal:2',
            'timeliness_rating' => 'decimal:2',
            'average_rating'    => 'decimal:3',
            'sort_order'        => 'integer',
        ];
    }

    /**
     * The average is recomputed automatically on every save, so no controller
     * has to remember to update it.
     */
    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            $item->average_rating = $item->computeAverage();
        });
    }

    /**
     * (Q + E + T) divided by however many are filled in.
     * Nulls are excluded - some outputs have no Timeliness dimension, and
     * treating those as zero would be wrong.
     */
    public function computeAverage(): ?float
    {
        $scores = collect([
            $this->quality_rating,
            $this->efficiency_rating,
            $this->timeliness_rating,
        ])->reject(fn($v) => $v === null || $v === '')
            ->map(fn($v) => (float) $v);

        return $scores->isEmpty() ? null : round($scores->avg(), 3);
    }

    public function ipcr(): BelongsTo
    {
        return $this->belongsTo(Ipcr::class);
    }

    /** Optional link back to the catalog - null for an employee's own entry. */
    public function jobFunction(): BelongsTo
    {
        return $this->belongsTo(JobFunction::class);
    }

    /**
     * What the employee reported, per measure, for a function with a rubric.
     *
     * Empty on a line typed by hand: those carry their marks directly and
     * have no figures behind them.
     */
    public function measures(): HasMany
    {
        return $this->hasMany(IpcrItemMeasure::class);
    }

    public function scopeOfCategory(Builder $query, FunctionCategory $category): Builder
    {
        return $query->where('category', $category);
    }
}

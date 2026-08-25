<?php

namespace App\Models;

use App\Enums\FunctionCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpcrItem extends Model
{
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
     * Kusang kinukwenta ang average tuwing sine-save.
     * Hindi na kailangang tandaan ng controller na i-update ito.
     */
    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            $item->average_rating = $item->computeAverage();
        });
    }

    /**
     * (Q + E + T) / bilang ng may laman.
     * Hindi isinasama ang null - may mga output na walang Timeliness dimension,
     * at mali kung ituturing nating zero ang mga 'yun.
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

    /** Opsyonal na link pabalik sa catalog - null kung sariling type ng empleyado. */
    public function jobFunction(): BelongsTo
    {
        return $this->belongsTo(JobFunction::class);
    }

    public function scopeOfCategory(Builder $query, FunctionCategory $category): Builder
    {
        return $query->where('category', $category);
    }
}

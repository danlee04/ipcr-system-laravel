<?php

namespace App\Models;

use App\Enums\FunctionCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Master catalog entry. This is NOT the IPCR line item - only a suggestion
 * the employee can pick when manually adding functions to their IPCR.
 */
class JobFunction extends Model
{
    protected $fillable = [
        'position_id',
        'designation_id',
        'category',
        'rating_category',
        'title',
        'success_indicator',
        'default_weight',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'category'        => FunctionCategory::class,
            'rating_category' => FunctionCategory::class,
            'default_weight'  => 'decimal:2',
            'is_active'       => 'boolean',
        ];
    }

    /**
     * Which of the three rated categories an IPCR line built from this
     * function belongs to.
     *
     * For strategic, core and support that is simply the function's own
     * category. "Common" only says the function is open to everyone, so it
     * carries a separate assignment - and returns null until HR makes it.
     */
    public function ratingCategory(): ?FunctionCategory
    {
        if ($this->category !== FunctionCategory::Common) {
            return $this->category;
        }

        return $this->rating_category;
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfCategory(Builder $query, FunctionCategory $category): Builder
    {
        return $query->where('category', $category);
    }

    /** The open pool - not tied to any position or designation. */
    public function scopeCommon(Builder $query): Builder
    {
        return $query->where('category', FunctionCategory::Common);
    }

    /**
     * Common functions HR has not yet filed under a rated category.
     *
     * Nobody can add these to an IPCR, so the admin screen surfaces them
     * rather than leaving them to fail at the point of use.
     */
    public function scopeNeedingRatingCategory(Builder $query): Builder
    {
        return $query->where('category', FunctionCategory::Common)
            ->whereNull('rating_category');
    }
}

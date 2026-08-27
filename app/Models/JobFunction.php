<?php

namespace App\Models;

use App\Enums\FunctionCategory;
use App\Enums\MeasureAnswer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'accomplishment_template',
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

    /**
     * How this function is graded, one row per measure it is rated on.
     *
     * Empty means it has no rubric: the assessor picks the marks by hand, the
     * way every function worked before rubrics existed.
     */
    public function measures(): HasMany
    {
        return $this->hasMany(FunctionMeasure::class);
    }

    /** Can this function grade itself from figures the employee reports? */
    public function hasRubric(): bool
    {
        return $this->measures->isNotEmpty();
    }

    /** The measures whose mark is worked out from a figure. */
    public function numericMeasures(): Collection
    {
        return $this->measures->filter(fn (FunctionMeasure $m): bool => $m->answer->isNumeric())->values();
    }

    /**
     * Every placeholder a template may use.
     *
     * With exactly one numeric measure the unqualified {value} is offered too,
     * because naming the measure in that case is ceremony - there is only one
     * figure it could mean.
     *
     * @return list<string>
     */
    public function placeholders(): array
    {
        $numeric = $this->numericMeasures();

        $tokens = $numeric->flatMap(fn (FunctionMeasure $m): array => $m->placeholders())->all();

        if ($numeric->count() === 1) {
            $tokens[] = '{value}';

            if ($numeric->first()->answer === MeasureAnswer::Count) {
                $tokens[] = '{ratio}';
            }
        }

        return $tokens;
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

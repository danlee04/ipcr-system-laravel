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
        'title',
        'success_indicator',
        'accomplishment_template',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'category'        => FunctionCategory::class,
            'is_active'       => 'boolean',
        ];
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

            if ($numeric->first()->readsAsDaysFromDeadline()) {
                $tokens[] = '{when}';
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
        // Qualified: this runs inside a join against positions, which carries
        // an is_active of its own.
        return $query->where($query->qualifyColumn('is_active'), true);
    }

    public function scopeOfCategory(Builder $query, FunctionCategory $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Open to everyone: tied to no position and no designation.
     *
     * A scope on the function, not a category of work. It still belongs to
     * Core, Support or Strategic like any other, which is why nothing has to
     * be asked about what it counts towards.
     */
    public function scopeForEveryone(Builder $query): Builder
    {
        return $query->whereNull('position_id')->whereNull('designation_id');
    }

    /** Does this reach the whole hospital? */
    public function reachesEveryone(): bool
    {
        return $this->position_id === null && $this->designation_id === null;
    }
}

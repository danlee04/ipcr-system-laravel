<?php

namespace App\Models;

use App\Enums\FunctionCategory;
use App\Enums\IpcrMode;
use App\Enums\IpcrStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ipcr extends Model
{
    use HasFactory;

    protected $table = 'ipcrs';

    protected $fillable = [
        'ipcr_period_id',
        'employee_id',
        'position_title',
        'office_name',
        'assessor_employee_id',
        'final_approver_employee_id',
        'status',
        'mode',
        'strategic_weight',
        'core_weight',
        'support_weight',
        'common_weight',
        'strategic_rating',
        'core_rating',
        'support_rating',
        'common_rating',
        'final_numerical_rating',
        'final_adjectival_rating',
        'submitted_at',
        'assessed_at',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'status'                 => IpcrStatus::class,
            'mode'                   => IpcrMode::class,
            'strategic_weight'       => 'decimal:2',
            'core_weight'            => 'decimal:2',
            'support_weight'         => 'decimal:2',
            'common_weight'          => 'decimal:2',
            'strategic_rating'       => 'decimal:3',
            'core_rating'            => 'decimal:3',
            'support_rating'         => 'decimal:3',
            'common_rating'          => 'decimal:3',
            'final_numerical_rating' => 'decimal:3',
            'submitted_at'           => 'datetime',
            'assessed_at'            => 'datetime',
            'approved_at'            => 'datetime',
        ];
    }

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function period(): BelongsTo
    {
        return $this->belongsTo(IpcrPeriod::class, 'ipcr_period_id');
    }

    /** The ratee - whose IPCR this is. */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** First step of the approval chain. */
    public function assessor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assessor_employee_id');
    }

    /** Final step - they set the final rating. */
    public function finalApprover(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'final_approver_employee_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(IpcrItem::class)->orderBy('sort_order');
    }

    /** Audit trail, most recent first. */
    public function approvals(): HasMany
    {
        return $this->hasMany(IpcrApproval::class)->latest('acted_at');
    }

    // ---------------------------------------------------------------
    // State checks
    // ---------------------------------------------------------------

    public function isEditableByOwner(): bool
    {
        return $this->status->isEditableByOwner();
    }

    /** Does the Actual Accomplishment field appear on this IPCR's items? */
    public function showsAccomplishment(): bool
    {
        return $this->mode->showsAccomplishment();
    }

    /**
     * Items that should carry an accomplishment but do not yet.
     * Always zero when the IPCR is targets_only.
     */
    public function itemsMissingAccomplishment(): int
    {
        if (! $this->showsAccomplishment()) {
            return 0;
        }

        return $this->items()
            ->where(function ($query): void {
                $query->whereNull('actual_accomplishment')
                    ->orWhere('actual_accomplishment', '');
            })
            ->count();
    }

    /**
     * The total weight carried by each category that has items.
     *
     * Only categories with items appear. An item with no weight counts as
     * zero rather than being skipped, so a blank weight shows up as a
     * shortfall instead of quietly vanishing.
     *
     * @return array<string, float>
     */
    public function weightTotalsByCategory(): array
    {
        return $this->items
            ->groupBy(fn (IpcrItem $item): string => $item->category instanceof FunctionCategory
                ? $item->category->value
                : (string) $item->category)
            ->map(fn ($lines): float => round(
                $lines->sum(fn (IpcrItem $item): float => (float) ($item->weight ?? 0)),
                2
            ))
            ->all();
    }

    /**
     * Categories whose weights do not add up to 100%, and what they add up to.
     *
     * A small tolerance is allowed so that a legitimate 33.33 + 33.33 + 33.34
     * is not rejected for being a hundredth out.
     *
     * @return array<string, float>
     */
    public function categoriesWithBadWeightTotals(float $tolerance = 0.01): array
    {
        return array_filter(
            $this->weightTotalsByCategory(),
            fn (float $total): bool => abs($total - 100.0) > $tolerance
        );
    }

    public function isAwaitingAssessment(): bool
    {
        return $this->status === IpcrStatus::Submitted;
    }

    public function isAwaitingFinalRating(): bool
    {
        return $this->status === IpcrStatus::Assessed;
    }

    public function isFinal(): bool
    {
        return $this->status->isFinal();
    }

    // ---------------------------------------------------------------
    // Scopes - these back each approver's inbox
    // ---------------------------------------------------------------

    public function scopeAwaitingAssessmentBy(Builder $query, Employee $approver): Builder
    {
        return $query->where('status', IpcrStatus::Submitted)
            ->where('assessor_employee_id', $approver->id);
    }

    public function scopeAwaitingFinalRatingBy(Builder $query, Employee $approver): Builder
    {
        return $query->where('status', IpcrStatus::Assessed)
            ->where('final_approver_employee_id', $approver->id);
    }

    public function scopeForPeriod(Builder $query, IpcrPeriod|int $period): Builder
    {
        return $query->where('ipcr_period_id', $period instanceof IpcrPeriod ? $period->id : $period);
    }
}

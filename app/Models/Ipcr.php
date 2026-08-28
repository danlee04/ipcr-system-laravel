<?php

namespace App\Models;

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
        'chain_overridden_at',
        'status',
        'mode',
        'strategic_weight',
        'core_weight',
        'support_weight',
        'strategic_rating',
        'core_rating',
        'support_rating',
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
            'strategic_rating'       => 'decimal:3',
            'core_rating'            => 'decimal:3',
            'support_rating'         => 'decimal:3',
            'final_numerical_rating' => 'decimal:3',
            'chain_overridden_at'    => 'datetime',
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

    /**
     * Was this handed in after the period's deadline?
     *
     * Read against the deadline as it stands rather than stamped at
     * submission. Extending a period is how an office forgives the people it
     * was extended for, and a frozen mark would leave them late for a date
     * that no longer exists.
     *
     * A deadline is a whole day: something handed in at half past eleven that
     * night is on time.
     */
    public function isLate(): bool
    {
        $deadline = $this->period?->submission_deadline;

        return $this->submitted_at !== null
            && $deadline !== null
            && $this->submitted_at->startOfDay()->greaterThan($deadline->startOfDay());
    }

    /** How many days past the deadline, or nought when it was not. */
    public function daysLate(): int
    {
        if (! $this->isLate()) {
            return 0;
        }

        return (int) $this->period->submission_deadline
            ->startOfDay()
            ->diffInDays($this->submitted_at->startOfDay());
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
     * Did HR or an administrator choose this chain by hand?
     *
     * Submission asks before resolving the chain from the org chart. Only a
     * chain somebody chose survives; one merely left over from an earlier
     * submission is resolved again, so a change of section head is picked up.
     */
    public function hasOverriddenChain(): bool
    {
        return $this->chain_overridden_at !== null
            && $this->assessor_employee_id !== null
            && $this->final_approver_employee_id !== null;
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

    /**
     * Every IPCR this employee is named on as assessor or final approver,
     * whatever its status.
     *
     * Backs the sidebar link, which must not disappear the moment the queue
     * empties - an approver still needs a way back to the inbox.
     */
    public function scopeRoutedTo(Builder $query, Employee $approver): Builder
    {
        return $query->where(function (Builder $inner) use ($approver): void {
            $inner->where('assessor_employee_id', $approver->id)
                ->orWhere('final_approver_employee_id', $approver->id);
        });
    }

    public function scopeForPeriod(Builder $query, IpcrPeriod|int $period): Builder
    {
        return $query->where('ipcr_period_id', $period instanceof IpcrPeriod ? $period->id : $period);
    }
}

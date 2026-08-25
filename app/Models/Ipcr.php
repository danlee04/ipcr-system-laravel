<?php

namespace App\Models;

use App\Enums\IpcrStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ipcr extends Model
{
    protected $table = 'ipcrs';

    protected $fillable = [
        'ipcr_period_id',
        'employee_id',
        'position_title',
        'office_name',
        'assessor_employee_id',
        'final_approver_employee_id',
        'status',
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

    /** Ang ratee - kung kanino ang IPCR na ito. */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** Unang hakbang ng approval chain. */
    public function assessor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assessor_employee_id');
    }

    /** Huling hakbang - siya ang naglalagay ng final rating. */
    public function finalApprover(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'final_approver_employee_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(IpcrItem::class)->orderBy('sort_order');
    }

    /** Audit trail, pinakabago muna. */
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
    // Scopes - ito ang gagamitin ng inbox ng bawat approver
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

<?php

namespace App\Models;

use App\Enums\ApprovalAction;
use App\Enums\ApprovalStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit trail. Every action is a new row, so the history stays
 * complete no matter how many times the IPCR is returned and resubmitted.
 *
 * Two kinds of row live here. An approver's row names the employee who acted.
 * An administrative row - a chain change, a reopening - names only the user
 * account, because HR and administrators need not be employees at all.
 */
class IpcrApproval extends Model
{
    protected $table = 'ipcr_approvals';

    protected $fillable = [
        'ipcr_id',
        'approver_employee_id',
        'acted_by_user_id',
        'stage',
        'action',
        'remarks',
        'acted_at',
    ];

    protected function casts(): array
    {
        return [
            'stage'    => ApprovalStage::class,
            'action'   => ApprovalAction::class,
            'acted_at' => 'datetime',
        ];
    }

    public function ipcr(): BelongsTo
    {
        return $this->belongsTo(Ipcr::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_employee_id');
    }

    /** The login that performed the action, when it was not an approver's. */
    public function actedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by_user_id');
    }

    /**
     * Who to show in the history.
     *
     * The employee record is preferred - it is the name on the printed form -
     * and the account name is the fallback for an administrative row. Neither
     * is guaranteed: an employee may have been deleted since.
     */
    public function actorName(): string
    {
        return $this->approver?->full_name
            ?? $this->actedBy?->name
            ?? 'Removed user';
    }

    /** Was this taken from outside the approval chain? */
    public function isAdministrative(): bool
    {
        return $this->stage === ApprovalStage::Administrative;
    }
}

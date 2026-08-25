<?php

namespace App\Models;

use App\Enums\ApprovalAction;
use App\Enums\ApprovalStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit trail. Every action is a new row, so the history stays
 * complete no matter how many times the IPCR is returned and resubmitted.
 */
class IpcrApproval extends Model
{
    protected $table = 'ipcr_approvals';

    protected $fillable = [
        'ipcr_id',
        'approver_employee_id',
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
}

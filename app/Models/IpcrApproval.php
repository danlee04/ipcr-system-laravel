<?php

namespace App\Models;

use App\Enums\ApprovalAction;
use App\Enums\ApprovalStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit trail. Bawat aksyon ay bagong row - kahit ilang beses
 * pang ma-return at ma-resubmit, buo ang kasaysayan.
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

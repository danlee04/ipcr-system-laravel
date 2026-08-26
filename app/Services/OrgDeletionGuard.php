<?php

namespace App\Services;

use App\Models\Designation;
use App\Models\Division;
use App\Models\IpcrPeriod;
use App\Models\Position;
use App\Models\Section;
use App\Support\DeletionReport;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Answers one question about an organizational record: what still references it?
 *
 * Deactivating is the normal way to retire a record, and the schema agrees -
 * every one of these tables carries `is_active` and their foreign keys use
 * restrictOnDelete. Deleting is the exception, allowed only when nothing points
 * at the record. Without this check the user would meet a raw foreign-key error
 * instead of a sentence telling them what is in the way.
 */
class OrgDeletionGuard
{
    public function for(Model $record): DeletionReport
    {
        $blockers = array_filter(match (true) {
            $record instanceof Division => [
                'sections'  => $record->sections()->count(),
                'employees' => $record->employees()->count(),
            ],
            $record instanceof Section => [
                'employees' => $record->employees()->count(),
            ],
            $record instanceof Position => [
                'job functions' => $record->jobFunctions()->count(),
                'employees'     => $record->employees()->count(),
            ],
            $record instanceof Designation => [
                'job functions' => $record->jobFunctions()->count(),
                'employees'     => $record->employees()->count(),
            ],
            // A period holding IPCRs is somebody's performance record. Closing
            // it is the way to retire it; deleting would take the IPCRs down
            // with it through the foreign key.
            $record instanceof IpcrPeriod => [
                'IPCRs' => $record->ipcrs()->count(),
            ],
            default => throw new InvalidArgumentException(
                'OrgDeletionGuard does not handle ' . $record::class . '.'
            ),
        }, fn (int $count): bool => $count > 0);

        return new DeletionReport(deletable: $blockers === [], blockers: $blockers);
    }
}

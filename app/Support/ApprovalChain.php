<?php

namespace App\Support;

use App\Models\Employee;

/**
 * The immutable result of resolving an approval chain - who assesses and
 * who gives the final approval, for one particular IPCR about to be
 * created or submitted.
 */
final readonly class ApprovalChain
{
    public function __construct(
        public Employee $assessor,
        public Employee $finalApprover,
    ) {}
}

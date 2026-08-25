<?php

namespace App\Support;

use App\Models\Employee;

/**
 * Simpleng immutable na resulta ng pag-resolve ng approval chain -
 * sino ang assessor, sino ang final approver, para sa isang partikular
 * na IPCR na gagawin/isasa-submit.
 */
final readonly class ApprovalChain
{
    public function __construct(
        public Employee $assessor,
        public Employee $finalApprover,
    ) {}
}

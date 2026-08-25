<?php

namespace App\Enums;

enum ApprovalStage: string
{
    case Assessment  = 'assessment';
    case FinalRating = 'final_rating';

    public function label(): string
    {
        return match ($this) {
            self::Assessment  => 'For Assessment',
            self::FinalRating => 'For Final Rating',
        };
    }

    /** The status the IPCR reaches once this stage is approved. */
    public function resultingStatus(): IpcrStatus
    {
        return match ($this) {
            self::Assessment  => IpcrStatus::Assessed,
            self::FinalRating => IpcrStatus::Approved,
        };
    }

    /** The status the IPCR must currently be in before this stage can be acted on. */
    public function requiredStatus(): IpcrStatus
    {
        return match ($this) {
            self::Assessment  => IpcrStatus::Submitted,
            self::FinalRating => IpcrStatus::Assessed,
        };
    }
}

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

    /** Anong status ang aabutin ng IPCR kapag na-approve ang stage na ito. */
    public function resultingStatus(): IpcrStatus
    {
        return match ($this) {
            self::Assessment  => IpcrStatus::Assessed,
            self::FinalRating => IpcrStatus::Approved,
        };
    }

    /** Anong status ang dapat na kasalukuyan bago pwedeng aksyunan ang stage na ito. */
    public function requiredStatus(): IpcrStatus
    {
        return match ($this) {
            self::Assessment  => IpcrStatus::Submitted,
            self::FinalRating => IpcrStatus::Assessed,
        };
    }
}

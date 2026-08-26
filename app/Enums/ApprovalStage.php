<?php

namespace App\Enums;

enum ApprovalStage: string
{
    case Assessment  = 'assessment';
    case FinalRating = 'final_rating';

    /**
     * Not a step the IPCR passes through: an action taken on it from outside
     * the chain, by HR or an administrator. It therefore has no required and
     * no resulting status.
     */
    case Administrative = 'administrative';

    public function label(): string
    {
        return match ($this) {
            self::Assessment     => 'For Assessment',
            self::FinalRating    => 'For Final Approval',
            self::Administrative => 'Administrative',
        };
    }

    /** The status the IPCR reaches once this stage is approved. */
    public function resultingStatus(): ?IpcrStatus
    {
        return match ($this) {
            self::Assessment     => IpcrStatus::Assessed,
            self::FinalRating    => IpcrStatus::Approved,
            self::Administrative => null,
        };
    }

    /** The status the IPCR must currently be in before this stage can be acted on. */
    public function requiredStatus(): ?IpcrStatus
    {
        return match ($this) {
            self::Assessment     => IpcrStatus::Submitted,
            self::FinalRating    => IpcrStatus::Assessed,
            self::Administrative => null,
        };
    }
}

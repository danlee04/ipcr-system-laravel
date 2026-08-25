<?php

namespace App\Enums;

/**
 * The owner chooses what their IPCR covers.
 *
 * In real CSC practice the IPCR is handled twice: at the start of the period
 * only targets are committed to, and at the end the actual accomplishments are
 * filled in. This is only a display preference - there is still a single
 * approval cycle - but it controls which fields appear and what is required
 * before submitting.
 */
enum IpcrMode: string
{
    case TargetsOnly        = 'targets_only';
    case WithAccomplishment = 'with_accomplishment';

    public function label(): string
    {
        return match ($this) {
            self::TargetsOnly        => 'Targets only',
            self::WithAccomplishment => 'Targets with accomplishments',
        };
    }

    /** Short explanation shown beside each choice. */
    public function description(): string
    {
        return match ($this) {
            self::TargetsOnly        => 'Commit to your targets now. You can add what you actually accomplished later.',
            self::WithAccomplishment => 'Record your targets together with what you actually accomplished.',
        };
    }

    /** Does the Actual Accomplishment field appear on each item? */
    public function showsAccomplishment(): bool
    {
        return $this === self::WithAccomplishment;
    }
}

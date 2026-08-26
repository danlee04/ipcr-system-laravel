<?php

namespace App\Enums;

/**
 * The kinds of rating period a CSC IPCR cycle uses.
 *
 * The ipcr_periods table has unique(year, type), so an agency running
 * semesters gets two periods a year and one running annual reviews gets one.
 */
enum IpcrPeriodType: string
{
    case FirstSemester  = 'first_semester';
    case SecondSemester = 'second_semester';
    case Annual         = 'annual';

    public function label(): string
    {
        return match ($this) {
            self::FirstSemester  => 'First Semester',
            self::SecondSemester => 'Second Semester',
            self::Annual         => 'Annual',
        };
    }

    /** A suggested period name, used to prefill the form. */
    public function suggestedName(int $year): string
    {
        return match ($this) {
            self::FirstSemester  => "January - June {$year}",
            self::SecondSemester => "July - December {$year}",
            self::Annual         => "Annual {$year}",
        };
    }
}

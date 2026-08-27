<?php

namespace App\Enums;

/**
 * The three dimensions a CSC output is rated on.
 *
 * Any of them may be n/a: plenty of outputs have no Timeliness at all, and a
 * measure left without a rubric is simply not rated - see
 * NotApplicableMeasureTest.
 */
enum RatingMeasure: string
{
    case Quality    = 'quality';
    case Efficiency = 'efficiency';
    case Timeliness = 'timeliness';

    public function label(): string
    {
        return match ($this) {
            self::Quality    => 'Quality',
            self::Efficiency => 'Efficiency',
            self::Timeliness => 'Timeliness',
        };
    }

    /** The single letter the CSC form uses, and the template placeholder. */
    public function key(): string
    {
        return match ($this) {
            self::Quality    => 'q',
            self::Efficiency => 'e',
            self::Timeliness => 't',
        };
    }

    /** The column on ipcr_items that carries this measure's mark. */
    public function column(): string
    {
        return $this->value . '_rating';
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

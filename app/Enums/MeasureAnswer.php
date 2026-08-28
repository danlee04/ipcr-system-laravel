<?php

namespace App\Enums;

/**
 * How one measure is answered when an employee reports what they accomplished.
 *
 * Each measure decides for itself. A function can be graded on a typed
 * percentage for Efficiency and on a picked descriptor for Quality, because
 * those are different questions.
 */
enum MeasureAnswer: string
{
    /** The rater picks one of the five written levels. Nothing is computed. */
    case Descriptor = 'descriptor';

    /** The employee types a figure, and the band it falls in is the mark. */
    case Number = 'number';

    /**
     * The employee gives two figures - 12 of 12 - and the percentage they
     * make is what the bands are read against. Always a percentage by
     * construction, so it carries no unit of its own.
     */
    case Count = 'count';

    public function label(): string
    {
        return match ($this) {
            self::Descriptor => 'Picking a descriptor',
            self::Number     => 'Typing a number',
            self::Count      => 'Counting out of a total — 12/12',
        };
    }

    /** Is the mark worked out from a figure rather than chosen? */
    public function isNumeric(): bool
    {
        return $this !== self::Descriptor;
    }

    /** Does it carry a unit, or is it a percentage by construction? */
    public function hasUnit(): bool
    {
        return $this === self::Number;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

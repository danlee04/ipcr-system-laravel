<?php

namespace App\Enums;

/**
 * CSC adjectival rating scale.
 *   4.500 - 5.000  Outstanding
 *   3.500 - 4.499  Very Satisfactory
 *   2.500 - 3.499  Satisfactory
 *   1.500 - 2.499  Unsatisfactory
 *   below 1.500    Poor
 */
enum AdjectivalRating: string
{
    case Outstanding      = 'Outstanding';
    case VerySatisfactory = 'Very Satisfactory';
    case Satisfactory     = 'Satisfactory';
    case Unsatisfactory   = 'Unsatisfactory';
    case Poor             = 'Poor';

    public static function fromNumeric(float $rating): self
    {
        return match (true) {
            $rating >= 4.5 => self::Outstanding,
            $rating >= 3.5 => self::VerySatisfactory,
            $rating >= 2.5 => self::Satisfactory,
            $rating >= 1.5 => self::Unsatisfactory,
            default        => self::Poor,
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Outstanding      => 'bg-mint-100 text-mint-800 ring-mint-500/25',
            self::VerySatisfactory => 'bg-brand-100 text-brand-800 ring-brand-500/25',
            self::Satisfactory     => 'bg-ink-100 text-ink-700 ring-ink-400/40',
            self::Unsatisfactory   => 'bg-amber-100 text-amber-800 ring-amber-500/20',
            self::Poor             => 'bg-red-100 text-red-800 ring-red-500/20',
        };
    }
}

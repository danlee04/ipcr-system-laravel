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
            self::Outstanding      => 'bg-emerald-100 text-emerald-800 ring-emerald-500/20',
            self::VerySatisfactory => 'bg-teal-100 text-teal-800 ring-teal-500/20',
            self::Satisfactory     => 'bg-blue-100 text-blue-800 ring-blue-500/20',
            self::Unsatisfactory   => 'bg-amber-100 text-amber-800 ring-amber-500/20',
            self::Poor             => 'bg-red-100 text-red-800 ring-red-500/20',
        };
    }
}

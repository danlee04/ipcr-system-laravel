<?php

namespace App\Enums;

/**
 * How DTRC engages a person.
 *
 * Only these three. The list used to carry the whole CSC vocabulary - casual,
 * contractual, coterminous - which the hospital does not hire under, so every
 * one of them was a wrong answer sitting in the dropdown waiting to be picked.
 */
enum EmploymentStatus: string
{
    case Permanent         = 'permanent';
    case JobOrder          = 'job_order';
    case ContractOfService = 'contract_of_service';

    /** Written the way it appears on the appointment paper. */
    public function label(): string
    {
        return match ($this) {
            self::Permanent         => 'Permanent',
            self::JobOrder          => 'Job Order',
            self::ContractOfService => 'Contract of Service',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

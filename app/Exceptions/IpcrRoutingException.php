<?php

namespace App\Exceptions;

use App\Models\Employee;
use RuntimeException;

/**
 * Itinatapon kapag hindi ma-resolve ng IpcrRoutingService ang approval chain
 * ng isang empleyado - hal. walang naka-assign na Section Head, o walang
 * naka-configure na Chief of Hospital sa buong system.
 *
 * Sadyang hindi ito silent-null: mas mabuting harangan ang pag-submit ng
 * IPCR kaysa tuluyan itong tumakbo nang walang assessor.
 */
class IpcrRoutingException extends RuntimeException
{
    public static function noSectionAssigned(Employee $employee): self
    {
        return new self(
            "Walang naka-assign na Section si {$employee->full_name}. " .
                'Kontakin ang HR/Admin para i-set ang section niya bago siya makapag-submit ng IPCR.'
        );
    }

    public static function noSectionHead(string $sectionName): self
    {
        return new self(
            "Walang naka-assign na Section Head sa '{$sectionName}'. " .
                'Kontakin ang HR/Admin para i-assign muna ang Section Head bago tumanggap ng IPCR submissions.'
        );
    }

    public static function noDivisionAssigned(Employee $employee): self
    {
        return new self(
            "Walang naka-assign na Division si {$employee->full_name}. " .
                'Kontakin ang HR/Admin para i-set ang division niya.'
        );
    }

    public static function noDivisionHead(string $divisionName): self
    {
        return new self(
            "Walang naka-assign na Division Head sa '{$divisionName}'. " .
                'Kontakin ang HR/Admin para i-assign muna ang Division Head.'
        );
    }

    public static function noChiefOfHospitalConfigured(): self
    {
        return new self(
            'Walang naka-mark na Chief of Hospital sa system (employees.is_chief_of_hospital). ' .
                'Kontakin ang Admin para i-set ito bago tumanggap ng IPCR submissions mula sa mga Division Head.'
        );
    }

    public static function chiefOfHospitalRequiresManualRouting(): self
    {
        return new self(
            'Ang IPCR ng Chief of Hospital ay wala pang automatic na approval chain sa system na ito. ' .
                'I-proseso ito nang manual sa pamamagitan ng Admin/HR interface, hindi sa pamamagitan ng ' .
                'karaniwang pag-submit ng IPCR.'
        );
    }
}

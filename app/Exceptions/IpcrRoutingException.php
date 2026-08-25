<?php

namespace App\Exceptions;

use App\Models\Employee;
use RuntimeException;

/**
 * Thrown when IpcrRoutingService cannot resolve an employee's approval chain -
 * for example, no Section Head is assigned, or no Chief of Hospital is
 * configured anywhere in the system.
 *
 * These deliberately are not silent nulls: it is better to block the
 * submission than to let an IPCR travel with no assessor.
 */
class IpcrRoutingException extends RuntimeException
{
    public static function noSectionAssigned(Employee $employee): self
    {
        return new self(
            "{$employee->full_name} has no Section assigned. " .
                'Ask HR/Admin to set their section before they can submit an IPCR.'
        );
    }

    public static function noSectionHead(string $sectionName): self
    {
        return new self(
            "No Section Head is assigned to '{$sectionName}'. " .
                'Ask HR/Admin to assign one before this section can accept IPCR submissions.'
        );
    }

    public static function noDivisionAssigned(Employee $employee): self
    {
        return new self(
            "{$employee->full_name} has no Division assigned. " .
                'Ask HR/Admin to set their division.'
        );
    }

    public static function noDivisionHead(string $divisionName): self
    {
        return new self(
            "No Division Head is assigned to '{$divisionName}'. " .
                'Ask HR/Admin to assign one.'
        );
    }

    public static function noChiefOfHospitalConfigured(): self
    {
        return new self(
            'No employee is marked as Chief of Hospital (employees.is_chief_of_hospital). ' .
                'Ask Admin to set this before accepting IPCR submissions from Division Heads.'
        );
    }

    public static function chiefOfHospitalRequiresManualRouting(): self
    {
        return new self(
            'The Chief of Hospital IPCR has no automatic approval chain in this system. ' .
                'Process it manually through the Admin/HR interface rather than through the ' .
                'usual IPCR submission.'
        );
    }
}

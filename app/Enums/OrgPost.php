<?php

namespace App\Enums;

use App\Models\Employee;

/**
 * An approving post: the reason an IPCR reaches somebody.
 *
 * Not a role. A role says what an account may do in this app - an HR user
 * manages the reference data - while a post says where the person sits in the
 * hospital. The two are independent, and one person often holds both: the
 * Section Head of HRD is usually also the HR user.
 *
 * The post is not stored on the employee. It IS the org chart:
 *
 *   Section Head      -> sections.section_head_employee_id
 *   Division Head     -> divisions.division_head_employee_id
 *   Chief of Hospital -> employees.is_chief_of_hospital
 *
 * Those columns are what IpcrRoutingService reads, so writing anywhere else
 * would give an answer the routing never sees.
 */
enum OrgPost: string
{
    case SectionHead      = 'section_head';
    case DivisionHead     = 'division_head';
    case ChiefOfHospital  = 'chief_of_hospital';

    public function label(): string
    {
        return match ($this) {
            self::SectionHead     => 'Section Head',
            self::DivisionHead    => 'Division Head',
            self::ChiefOfHospital => 'Chief of Hospital',
        };
    }

    /** What each post is for, shown beside the choice. */
    public function description(): string
    {
        return match ($this) {
            self::SectionHead     => 'Assesses everyone in their section.',
            self::DivisionHead    => 'Gives the final approval for their division, and assesses its Section Heads. Holds no section of their own.',
            self::ChiefOfHospital => 'Assesses and approves every Division Head. Only one person holds this.',
        };
    }

    /**
     * The post this employee holds right now, read back off the org chart.
     *
     * Most senior first: one person can appear in two of those columns, and
     * the highest is the one that decides how their own IPCR is routed.
     */
    public static function heldBy(Employee $employee): ?self
    {
        return match (true) {
            $employee->isChiefOfHospital() => self::ChiefOfHospital,
            $employee->isDivisionHead()    => self::DivisionHead,
            $employee->isSectionHead()     => self::SectionHead,
            default                        => null,
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

<?php

namespace App\Services;

use App\Exceptions\IpcrRoutingException;
use App\Models\Employee;
use App\Support\ApprovalChain;

/**
 * This service answers one question: "If this employee submits an IPCR,
 * who should assess it and who gives the final approval?"
 *
 * Based on the agreed flow:
 *   Employee      -> Section Head (assessment)  -> Division Head (final)
 *   Section Head  -> Division Head (assessment)  -> Chief of Hospital (final)
 *   Division Head -> Chief of Hospital (assessment AND final - the same
 *                     person, but still two steps in the status flow)
 *   Chief of Hospital -> NO automatic routing. Handled manually by Admin/HR.
 *
 * The order of the checks below MATTERS: test for Chief of Hospital first,
 * then Division Head, then Section Head, BEFORE falling back to "has a
 * section" - a promoted Division Head may still carry a section_id on their
 * record.
 */
class IpcrRoutingService
{
    /**
     * @throws IpcrRoutingException when the chain cannot be resolved - for
     *         example no head is assigned, or the employee is the Chief of
     *         Hospital, which needs manual routing.
     */
    public function resolve(Employee $employee): ApprovalChain
    {
        if ($employee->isChiefOfHospital()) {
            throw IpcrRoutingException::chiefOfHospitalRequiresManualRouting();
        }

        if ($employee->isDivisionHead()) {
            return $this->resolveForDivisionHead($employee);
        }

        if ($employee->isSectionHead()) {
            return $this->resolveForSectionHead($employee);
        }

        return $this->resolveForRankAndFile($employee);
    }

    /**
     * Rank and file (or a Section Head holding no section - an edge case):
     *   assessor      = the Section Head of their section
     *   finalApprover = the Division Head of that section's division
     */
    private function resolveForRankAndFile(Employee $employee): ApprovalChain
    {
        $section = $employee->section;

        if ($section === null) {
            throw IpcrRoutingException::noSectionAssigned($employee);
        }

        $sectionHead = $section->head;

        if ($sectionHead === null) {
            throw IpcrRoutingException::noSectionHead($section->name);
        }

        $division = $section->division;

        if ($division === null) {
            throw IpcrRoutingException::noDivisionAssigned($employee);
        }

        $divisionHead = $division->head;

        if ($divisionHead === null) {
            throw IpcrRoutingException::noDivisionHead($division->name);
        }

        return new ApprovalChain(assessor: $sectionHead, finalApprover: $divisionHead);
    }

    /**
     * Section Head:
     *   assessor      = the Division Head of the division their section sits in
     *   finalApprover = Chief of Hospital
     */
    private function resolveForSectionHead(Employee $employee): ApprovalChain
    {
        $section = $employee->headedSection;
        $division = $section?->division;

        if ($division === null) {
            throw IpcrRoutingException::noDivisionAssigned($employee);
        }

        $divisionHead = $division->head;

        if ($divisionHead === null) {
            throw IpcrRoutingException::noDivisionHead($division->name);
        }

        return new ApprovalChain(assessor: $divisionHead, finalApprover: $this->chiefOfHospitalOrFail());
    }

    /**
     * Division Head:
     *   assessor = finalApprover = Chief of Hospital (the same person, but
     *   still two steps in the status flow: submitted -> assessed -> approved)
     */
    private function resolveForDivisionHead(Employee $employee): ApprovalChain
    {
        $chief = $this->chiefOfHospitalOrFail();

        return new ApprovalChain(assessor: $chief, finalApprover: $chief);
    }

    private function chiefOfHospitalOrFail(): Employee
    {
        $chief = Employee::query()
            ->active()
            ->where('is_chief_of_hospital', true)
            ->first();

        if ($chief === null) {
            throw IpcrRoutingException::noChiefOfHospitalConfigured();
        }

        return $chief;
    }
}

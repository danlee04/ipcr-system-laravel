<?php

namespace App\Services;

use App\Exceptions\IpcrRoutingException;
use App\Models\Employee;
use App\Support\ApprovalChain;

/**
 * Sinasagot ng service na ito ang tanong: "Kung mag-susubmit ng IPCR
 * ang empleyadong ito, sino ang dapat na assessor at sino ang final
 * approver?"
 *
 * Batay sa flow na tinukoy:
 *   Employee      -> Section Head (assessment)  -> Division Head (final)
 *   Section Head  -> Division Head (assessment)  -> Chief of Hospital (final)
 *   Division Head -> Chief of Hospital (assessment AT final - parehong tao,
 *                     dalawang hakbang pa rin sa status flow)
 *   Chief of Hospital -> WALANG automatic routing. Manual sa Admin/HR.
 *
 * Ang pagkakasunod ng pagsuri sa ibaba ay MAHALAGA: kailangang tingnan
 * muna kung Chief of Hospital, tapos Division Head, tapos Section Head,
 * BAGO tingnan kung may section lang siya - dahil posibleng may section_id
 * pa rin sa record ng isang Division Head kung galing siya sa promotion.
 */
class IpcrRoutingService
{
    /**
     * @throws IpcrRoutingException kapag hindi ma-resolve ang chain -
     *         hal. walang naka-assign na head, o Chief of Hospital ang
     *         empleyado (manual routing ang kailangan doon).
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
     * Rank & file (o Section Head na walang hawak na section - edge case):
     *   assessor      = Section Head ng section niya
     *   finalApprover = Division Head ng division ng section niya
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
     *   assessor      = Division Head ng division ng section na hawak niya
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
     *   assessor = finalApprover = Chief of Hospital (parehong tao,
     *   dalawang hakbang pa rin sa status flow - submitted -> assessed -> approved)
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

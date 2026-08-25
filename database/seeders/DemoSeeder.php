<?php

namespace Database\Seeders;

use App\Enums\FunctionCategory;
use App\Models\Designation;
use App\Models\Division;
use App\Models\Employee;
use App\Models\IpcrPeriod;
use App\Models\JobFunction;
use App\Models\Position;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Panimulang datos para masubukan ang buong IPCR flow sa browser.
 *
 * Ang binubuong kompletong chain:
 *   Juan (rank & file, Nursing Section)
 *     -> assessor:       Maria (Section Head ng Nursing Section)
 *     -> final approver: Ramon (Division Head ng Medical Services)
 *
 * Idempotent lahat - ligtas ulit-ulitin ang `php artisan db:seed`.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $division = Division::firstOrCreate(
            ['code' => 'MSD'],
            ['name' => 'Medical Services Division', 'is_active' => true],
        );

        $section = Section::firstOrCreate(
            ['code' => 'NURS'],
            ['division_id' => $division->id, 'name' => 'Nursing Section', 'is_active' => true],
        );

        $nurse = Position::firstOrCreate(
            ['item_number' => 'DTRC-N2-001'],
            ['title' => 'Nurse II', 'salary_grade' => 16, 'is_active' => true],
        );

        $supervisor = Position::firstOrCreate(
            ['item_number' => 'DTRC-NS-001'],
            ['title' => 'Nurse Supervisor', 'salary_grade' => 22, 'is_active' => true],
        );

        $medicalOfficer = Position::firstOrCreate(
            ['item_number' => 'DTRC-MO4-001'],
            ['title' => 'Medical Officer IV', 'salary_grade' => 24, 'is_active' => true],
        );

        // --- Mga tao -------------------------------------------------

        // Ang test account mo. Hindi ginagalaw ang password kung existing na.
        $juan = $this->employeeFor(
            email: 'test@example.com',
            name: 'Juan Dela Cruz',
            attributes: [
                'employee_number' => 'DTRC-0001',
                'first_name'      => 'Juan',
                'last_name'       => 'Dela Cruz',
                'position_id'     => $nurse->id,
                'section_id'      => $section->id,
                'division_id'     => $division->id,
            ],
        );

        $maria = $this->employeeFor(
            email: 'sectionhead@example.com',
            name: 'Maria Santos',
            attributes: [
                'employee_number' => 'DTRC-0002',
                'first_name'      => 'Maria',
                'last_name'       => 'Santos',
                'position_id'     => $supervisor->id,
                'section_id'      => $section->id,
                'division_id'     => $division->id,
            ],
        );

        $ramon = $this->employeeFor(
            email: 'divisionhead@example.com',
            name: 'Ramon Bautista',
            attributes: [
                'employee_number' => 'DTRC-0003',
                'first_name'      => 'Ramon',
                'last_name'       => 'Bautista',
                'position_id'     => $medicalOfficer->id,
                'division_id'     => $division->id,
            ],
        );

        // Dito nabubuo ang approval chain - ang head columns ang binabasa
        // ng IpcrRoutingService, hindi ang position title.
        $section->update(['section_head_employee_id' => $maria->id]);
        $division->update(['division_head_employee_id' => $ramon->id]);

        // --- Designation ni Juan -------------------------------------

        $oicBudget = Designation::firstOrCreate(
            ['title' => 'OIC - Budget'],
            ['description' => 'Officer-in-Charge, Budget Section', 'is_active' => true],
        );

        $juan->designations()->syncWithoutDetaching([
            $oicBudget->id => [
                'start_date'      => now()->startOfYear()->toDateString(),
                'order_reference' => 'Office Order No. 2026-001',
                'is_active'       => true,
            ],
        ]);

        // --- Job functions -------------------------------------------
        // Core  -> nakakabit sa PLANTILLA POSITION
        // Strat/Support -> nakakabit sa DESIGNATION
        // Common -> walang kabit, bukas sa lahat

        $this->jobFunction($nurse->id, null, FunctionCategory::Core, 'Nagbibigay ng direktang pangangalaga sa pasyente', 'Nakapagbigay ng nursing care sa lahat ng naka-assign na pasyente kada shift', 30);
        $this->jobFunction($nurse->id, null, FunctionCategory::Core, 'Nagtatala ng vital signs at nursing notes', 'Kumpleto at napapanahon ang tala sa loob ng shift', 25);

        $this->jobFunction(null, $oicBudget->id, FunctionCategory::Strategic, 'Naghahanda ng taunang budget proposal', 'Naisumite ang budget proposal bago ang deadline ng DBM', 20);
        $this->jobFunction(null, $oicBudget->id, FunctionCategory::Support, 'Nagsusuri ng buwanang financial report', 'Nasuri at naisumite ang report kada ika-5 ng buwan', 15);

        $this->jobFunction(null, null, FunctionCategory::Common, 'Dumadalo sa mga pulong at training ng ahensya', 'Hindi bababa sa 80% attendance sa mga itinakdang pulong', 5);
        $this->jobFunction(null, null, FunctionCategory::Common, 'Sumusunod sa oras ng pagpasok at pag-uwi', 'Walang unauthorized absence o tardiness', 5);

        // --- Bukas na rating period ----------------------------------

        IpcrPeriod::firstOrCreate(
            ['year' => (int) now()->year, 'type' => 'first_semester'],
            [
                'name'                => 'January - June ' . now()->year,
                'start_date'          => now()->startOfYear()->toDateString(),
                'end_date'            => now()->endOfYear()->toDateString(),
                'submission_deadline' => now()->addMonth()->toDateString(),
                'status'              => 'open',
            ],
        );
    }

    /**
     * Gumagawa (o hinahanap) ng User at ng kaugnay nitong Employee record.
     * Kapag existing na ang user, hindi na pinapalitan ang password niya.
     */
    private function employeeFor(string $email, string $name, array $attributes): Employee
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make('password')],
        );

        return Employee::updateOrCreate(
            ['user_id' => $user->id],
            $attributes + [
                'date_hired'        => now()->subYears(3)->toDateString(),
                'employment_status' => 'permanent',
                'is_active'         => true,
            ],
        );
    }

    private function jobFunction(?int $positionId, ?int $designationId, FunctionCategory $category, string $title, string $indicator, float $weight): void
    {
        JobFunction::firstOrCreate(
            ['title' => $title, 'category' => $category],
            [
                'position_id'       => $positionId,
                'designation_id'    => $designationId,
                'success_indicator' => $indicator,
                'default_weight'    => $weight,
                'is_active'         => true,
            ],
        );
    }
}

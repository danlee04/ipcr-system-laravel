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
 * Starter data for exercising the whole IPCR flow in the browser.
 *
 * The complete chain it builds:
 *   Juan (rank & file, Nursing Section)
 *     -> assessor:       Maria (Section Head of the Nursing Section)
 *     -> final approver: Ramon (Division Head of Medical Services)
 *
 * Everything is idempotent - `php artisan db:seed` is safe to re-run.
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

        // --- People --------------------------------------------------

        // Your test account. An existing password is left untouched.
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

        // This is what forms the approval chain - IpcrRoutingService reads the
        // head columns, not the position title.
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
        // Core  -> attached to the PLANTILLA POSITION
        // Strategic/Support -> attached to the DESIGNATION
        // Common -> attached to nothing, open to everyone

        $this->jobFunction($nurse->id, null, FunctionCategory::Core, 'Provides direct patient care', 'Nursing care delivered to every assigned patient each shift', 30);
        $this->jobFunction($nurse->id, null, FunctionCategory::Core, 'Records vital signs and nursing notes', 'Records complete and up to date within the shift', 25);

        $this->jobFunction(null, $oicBudget->id, FunctionCategory::Strategic, 'Prepares the annual budget proposal', 'Budget proposal submitted before the DBM deadline', 20);
        $this->jobFunction(null, $oicBudget->id, FunctionCategory::Support, 'Reviews the monthly financial report', 'Report reviewed and submitted by the 5th of each month', 15);

        $this->jobFunction(null, null, FunctionCategory::Support, 'Attends agency meetings and training', 'At least 80% attendance at scheduled meetings', 5);
        $this->jobFunction(null, null, FunctionCategory::Support, 'Observes official working hours', 'No unauthorized absence or tardiness', 5);

        // --- Open rating period --------------------------------------

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

        // --- Administrator -------------------------------------------

        // Deliberately has no Employee record. A system administrator and a
        // rated employee are different things, and keeping them apart stops
        // the admin screens from quietly depending on an employee existing.
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'System Administrator', 'password' => Hash::make('password')],
        );

        $admin->syncRoles(['admin']);
    }

    /**
     * Creates (or finds) a User and its matching Employee record.
     * An existing user keeps their current password.
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

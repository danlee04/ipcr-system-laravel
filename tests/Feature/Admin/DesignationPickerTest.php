<?php

namespace Tests\Feature\Admin;

use App\Models\Designation;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ticking designations on the employee form, and unticking them.
 *
 * Two things were wrong with the picker. Untying the last one could not be
 * saved: a browser sends nothing at all for a set of checkboxes with none
 * ticked, so the request was indistinguishable from one that never carried the
 * field, and the form left their designations alone. And every designation was
 * offered to everybody, so the same OIC post could quietly be given to two
 * people at once - and then whichever posting was newest decided the chain.
 */
class DesignationPickerTest extends TestCase
{
    use RefreshDatabase;

    private Division $division;

    private Section $section;

    private Position $position;

    private Designation $oic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->division = Division::factory()->create(['name' => 'Administrative Division']);
        $this->section = Section::factory()->create([
            'division_id' => $this->division->id,
            'name'        => 'Human Resource Development Section',
        ]);
        $this->position = Position::factory()->create([
            'section_id' => $this->section->id,
            'title'      => 'Administrative Officer III',
        ]);

        $this->oic = Designation::factory()->create(['title' => 'OIC - HRMO']);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function employee(string $last, array $designations = []): Employee
    {
        $employee = Employee::factory()->create([
            'last_name'   => $last,
            'division_id' => $this->division->id,
            'section_id'  => $this->section->id,
            'position_id' => $this->position->id,
        ]);

        foreach ($designations as $designation) {
            $employee->designations()->attach($designation->id, ['is_active' => true]);
        }

        return $employee->fresh();
    }

    /** The fields the form always sends, whatever the administrator changed. */
    private function payload(Employee $employee, array $overrides = []): array
    {
        return array_merge([
            'first_name'        => $employee->first_name,
            'last_name'         => $employee->last_name,
            'employee_number'   => $employee->employee_number,
            'employment_status' => 'permanent',
            'division_id'       => $this->division->id,
            'section_id'        => $this->section->id,
            'position_id'       => $this->position->id,

            // The picker was on the form. A browser cannot say "none of them"
            // any other way.
            'designations_offered' => '1',
        ], $overrides);
    }

    private function page(): string
    {
        return $this->actingAs($this->admin())
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->getContent();
    }

    /**
     * How many forms on the page offer this designation as a tickable box.
     *
     * The page carries one create form and one edit form per employee, and the
     * title also appears in the list itself - so counting the checkbox is the
     * only way to ask which forms actually offer it.
     */
    private function offeredBy(string $html, Designation $designation): int
    {
        return substr_count($html, 'name="designations[]" value="' . $designation->id . '"');
    }

    // -----------------------------------------------------------------
    // Untying the last one
    // -----------------------------------------------------------------

    public function test_unticking_every_designation_takes_it_away(): void
    {
        $employee = $this->employee('Guico', [$this->oic]);

        // Exactly what a browser sends when the boxes are all clear: the field
        // is not in the request at all.
        $this->actingAs($this->admin())
            ->put(route('admin.employees.update', $employee), $this->payload($employee))
            ->assertSessionHasNoErrors();

        $this->assertCount(0, $employee->fresh()->activeDesignations);
    }

    /** The saving above only works because the form actually sends it. */
    public function test_the_form_carries_the_marker_that_makes_that_possible(): void
    {
        $this->employee('Guico', [$this->oic]);

        $this->assertStringContainsString(
            'name="designations_offered" value="1"',
            $this->page(),
        );
    }

    /** A form that never showed the picker must not clear what they hold. */
    public function test_a_form_without_the_picker_leaves_them_alone(): void
    {
        $employee = $this->employee('Guico', [$this->oic]);

        $payload = $this->payload($employee);
        unset($payload['designations_offered']);

        $this->actingAs($this->admin())
            ->put(route('admin.employees.update', $employee), $payload)
            ->assertSessionHasNoErrors();

        $this->assertCount(1, $employee->fresh()->activeDesignations);
    }

    // -----------------------------------------------------------------
    // One holder at a time
    // -----------------------------------------------------------------

    public function test_a_designation_somebody_holds_is_not_offered_to_anybody_else(): void
    {
        $this->employee('Guico', [$this->oic]);
        $this->employee('Madelo');

        // Only the holder's own form still offers it: not the create form, and
        // not the other employee's.
        $this->assertSame(1, $this->offeredBy($this->page(), $this->oic));
    }

    public function test_the_holder_is_still_offered_their_own_and_it_is_ticked(): void
    {
        $this->employee('Guico', [$this->oic]);

        preg_match_all(
            '/<input[^>]*name="designations\[\]" value="' . $this->oic->id . '"[^>]*>/',
            $this->page(),
            $boxes,
        );

        $this->assertCount(1, $boxes[0], 'Only the holder\'s own form should offer it.');
        $this->assertStringContainsString('checked', $boxes[0][0]);
    }

    /** Let go of, it goes back into the pool. */
    public function test_a_designation_nobody_holds_is_offered_everywhere(): void
    {
        $employee = $this->employee('Guico');
        $employee->designations()->attach($this->oic->id, ['is_active' => false]);

        // The create form and this employee's own.
        $this->assertSame(2, $this->offeredBy($this->page(), $this->oic));
    }

    /**
     * A retired employee should not hold a post hostage.
     *
     * HR deactivates somebody and then has to hand their OIC post on. If the
     * deactivated record kept its claim, the only way out would be to switch
     * them back on, untick, and switch them off again.
     */
    public function test_a_deactivated_employee_does_not_reserve_one(): void
    {
        $employee = $this->employee('Retired', [$this->oic]);
        $employee->update(['is_active' => false]);

        $this->assertSame(2, $this->offeredBy($this->page(), $this->oic));
    }
}

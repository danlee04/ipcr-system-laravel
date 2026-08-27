<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrgPost;
use App\Http\Controllers\Concerns\RendersLiveLists;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEmployeeRequest;
use App\Http\Requests\Admin\UpdateEmployeeRequest;
use App\Models\Designation;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Employees, and the login accounts that belong to them.
 *
 * Public registration is closed, so this screen is the only way an account
 * comes into existence. Creating the person and their login together is the
 * point: it guarantees every login is attached to a division, section and
 * position, which is what IpcrRoutingService needs to route an IPCR.
 */
class EmployeeController extends Controller
{
    use RendersLiveLists;

    /** Rows per page on every admin list. */
    private const PER_PAGE = 20;

    public function index(Request $request): View
    {
        $employees = Employee::query()
            ->with(['position', 'section.division', 'division', 'user', 'activeDesignations', 'headedSection', 'headedDivision'])
            ->when($request->string('search')->trim()->value(), $this->searchFor(...))
            ->when($request->integer('division'), fn ($q, int $id) => $q->where('division_id', $id))
            ->when($request->integer('section'), fn ($q, int $id) => $q->where('section_id', $id))
            ->when(
                in_array($request->query('status'), ['active', 'inactive'], true),
                fn ($q) => $q->where('is_active', $request->query('status') === 'active')
            )
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $divisions = Division::query()->orderBy('name')->get();
        $sections = Section::query()->with('division')->orderBy('name')->get();
        // The section comes with it: the position picker narrows on it, and on
        // the division behind it, so the three placement selects can never
        // describe a combination that does not exist.
        $positions = Position::query()->with('section')->orderBy('title')->get();

        return $this->liveList($request, 'admin.employees.index', 'admin.employees.rows',
            compact('employees', 'divisions', 'sections', 'positions') + [
                // A designation can sit anywhere in the hospital, so the whole
                // list is offered rather than one narrowed by the placement.
                'designations' => Designation::query()->active()->orderBy('title')->get(),
            ]);
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $temporaryPassword = null;

        $employee = DB::transaction(function () use ($data, &$temporaryPassword): Employee {
            $employee = Employee::create($this->employeeAttributes($data) + ['is_active' => true]);

            if (! empty($data['email'])) {
                $temporaryPassword = $this->createAccountFor($employee, $data);
            }

            $this->applyPost($employee, $data);
            $this->applyDesignations($employee, $data);

            return $employee;
        });

        $message = "Created employee \"{$employee->full_name}\".";

        if ($temporaryPassword !== null) {
            $message .= " Temporary password: {$temporaryPassword} — give this to them; it is shown only once.";
        }

        return redirect()->route('admin.employees.index')->with('status', $message);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $data = $request->validated();
        $temporaryPassword = null;

        DB::transaction(function () use ($data, $employee, &$temporaryPassword): void {
            $employee->update($this->employeeAttributes($data));

            if ($employee->user !== null) {
                // Keep the account in step with the record it belongs to.
                $employee->user->update(array_filter([
                    'name'  => $employee->fresh()->full_name,
                    'email' => $data['email'] ?? null,
                ]));

                if (! empty($data['role'])) {
                    $employee->user->syncRoles([$data['role']]);
                }

                // Resetting the password of an account that already exists.
                // Blank means "leave it alone", not "clear it".
                if (! empty($data['password'])) {
                    $employee->user->update(['password' => Hash::make($data['password'])]);
                }
            } elseif (! empty($data['email'])) {
                // Giving an existing employee a login for the first time.
                $temporaryPassword = $this->createAccountFor($employee, $data);
            }

            $this->applyPost($employee, $data);
            $this->applyDesignations($employee, $data);
        });

        $message = "Updated employee \"{$employee->fresh()->full_name}\".";

        if ($temporaryPassword !== null) {
            $message .= " Temporary password: {$temporaryPassword} — give this to them; it is shown only once.";
        }

        return redirect()->route('admin.employees.index')->with('status', $message);
    }

    public function setActive(Request $request, Employee $employee): RedirectResponse
    {
        // An explicit value, not a toggle: two tabs disagreeing about the
        // current state would otherwise flip it the wrong way.
        $validated = $request->validate(['active' => ['required', 'boolean']]);

        $employee->update(['is_active' => $validated['active']]);

        return redirect()->route('admin.employees.index')->with(
            'status',
            ($validated['active'] ? 'Activated' : 'Deactivated') . " employee \"{$employee->full_name}\"."
        );
    }

    /**
     * Soft delete only. IPCR history hangs off the employee record, so the row
     * has to survive; OrgDeletionGuard is not consulted here for that reason.
     */
    public function destroy(Employee $employee): RedirectResponse
    {
        $name = $employee->full_name;
        $employee->delete();

        return redirect()->route('admin.employees.index')
            ->with('status', "Removed employee \"{$name}\". Their IPCR history is kept.");
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * Name, employee number, or the email on their account.
     *
     * The term is split on whitespace and every word must match somewhere, so
     * "maria santos" finds a Maria Santos whose names live in two columns. No
     * SQL concatenation is used: `||` means OR in MySQL and concat in SQLite,
     * and this app runs on both.
     */
    private function searchFor(Builder $query, string $term): Builder
    {
        foreach (preg_split('/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $like = '%' . $word . '%';

            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('middle_name', 'like', $like)
                    ->orWhere('employee_number', 'like', $like)
                    ->orWhereHas('user', fn (Builder $user) => $user->where('email', 'like', $like));
            });
        }

        return $query;
    }

    /** The employee columns, without the account fields the form also carries. */
    private function employeeAttributes(array $data): array
    {
        return [
            'first_name'           => $data['first_name'],
            'middle_name'          => $data['middle_name'] ?? null,
            'last_name'            => $data['last_name'],
            'suffix'               => $data['suffix'] ?? null,
            'employee_number'      => $data['employee_number'],
            'position_id'          => $data['position_id'] ?? null,
            'division_id'          => $data['division_id'] ?? null,
            'section_id'           => $data['section_id'] ?? null,
            'employment_status'    => $data['employment_status'],
        ];
    }

    /**
     * Creates the login, links it, and returns the password to show once.
     *
     * A password typed on the form wins; a blank one is generated. Either way
     * it comes back to be shown, because a password nobody can read is a
     * locked account.
     */
    private function createAccountFor(Employee $employee, array $data): ?string
    {
        $chosen = $data['password'] ?? null;
        $chosen = is_string($chosen) && $chosen !== '' ? $chosen : null;

        // Only a generated one is worth reporting back. Reading an
        // administrator their own password onto the screen tells them nothing
        // and puts it somewhere it need not be.
        $password = $chosen ?? Str::password(12, symbols: false);

        $user = User::create([
            'name'     => $employee->full_name,
            'email'    => $data['email'],
            'password' => Hash::make($password),
        ]);

        $user->syncRoles([$data['role'] ?? 'employee']);
        $employee->update(['user_id' => $user->id]);

        return $chosen === null ? $password : null;
    }

    /**
     * The posts this employee is designated to.
     *
     * A designation can sit anywhere - the OIC of HRD may be on another
     * section's plantilla entirely - so it is deliberately not checked against
     * their division or section.
     *
     * syncWithPivotValues, not sync: FunctionCatalogService reads only the
     * ACTIVE rows, so a designation attached without is_active would be
     * recorded and still reach nobody.
     */
    private function applyDesignations(Employee $employee, array $data): void
    {
        if (! array_key_exists('designations', $data)) {
            return;   // The form did not ask; leave what they hold alone.
        }

        $employee->designations()->syncWithPivotValues(
            $data['designations'] ?? [],
            ['is_active' => true]
        );
    }

    /**
     * Writes the chosen post onto the org chart.
     *
     * The post is not a column on the employee - it IS the org chart, and the
     * org chart is what IpcrRoutingService reads. Storing it anywhere else
     * would give an answer the routing never sees.
     *
     * The field states the present, so standing down comes first: whatever
     * they held and no longer hold is released before the new post is taken
     * up. Without that, moving a Section Head to another section would leave
     * the old section with a head who no longer leads it.
     */
    private function applyPost(Employee $employee, array $data): void
    {
        $employee->refresh();
        $post = OrgPost::tryFrom((string) ($data['post'] ?? ''));

        // What they lead, which is not always where they sit. Someone on the
        // Health Information Management plantilla can be Section Head of HRD;
        // reading the headship off section_id would put them at the head of
        // the wrong section and leave the right one with none.
        $headsSection = $data['heads_section_id'] ?? $employee->section_id;
        $headsDivision = $data['heads_division_id'] ?? $employee->division_id;

        Section::query()
            ->where('section_head_employee_id', $employee->id)
            ->when(
                $post === OrgPost::SectionHead && $headsSection,
                fn (Builder $query) => $query->whereKeyNot($headsSection)
            )
            ->update(['section_head_employee_id' => null]);

        Division::query()
            ->where('division_head_employee_id', $employee->id)
            ->when(
                $post === OrgPost::DivisionHead && $headsDivision,
                fn (Builder $query) => $query->whereKeyNot($headsDivision)
            )
            ->update(['division_head_employee_id' => null]);

        $employee->update(['is_chief_of_hospital' => $post === OrgPost::ChiefOfHospital]);

        match ($post) {
            // Exactly one Chief of Hospital: IpcrRoutingService takes the
            // first active employee carrying the flag, so a second would
            // silently decide every Division Head's chain by row order.
            OrgPost::ChiefOfHospital => Employee::query()
                ->where('is_chief_of_hospital', true)
                ->whereKeyNot($employee->id)
                ->update(['is_chief_of_hospital' => false]),

            // One head per section and per division, so taking the post up
            // replaces whoever held it - no separate demotion needed.
            OrgPost::SectionHead => Section::query()
                ->whereKey($headsSection)
                ->update(['section_head_employee_id' => $employee->id]),

            OrgPost::DivisionHead => Division::query()
                ->whereKey($headsDivision)
                ->update(['division_head_employee_id' => $employee->id]),

            default => null,
        };
    }
}

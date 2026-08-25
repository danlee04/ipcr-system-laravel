# Admin Organizational Data Management (Phase 1) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give administrators two screens for managing organizational reference data — Organization (divisions, their sections, and both head assignments) and Job Titles (positions and designations) — behind a real role-based access layer.

**Architecture:** `spatie/laravel-permission` is already installed and migrated but entirely unwired; Task 1 connects it. Two page controllers own the screens, four model controllers own the writes, and an `OrgDeletionGuard` service answers "what still references this record?" so deleting is refused with counts rather than a foreign-key crash. Deactivating is the normal retirement path; deleting is the exception.

**Tech Stack:** Laravel 12, PHP 8.4, spatie/laravel-permission ^8.3, Blade + Alpine 3, Tailwind v4, PHPUnit 12.

**Spec:** `docs/superpowers/specs/2026-08-25-admin-org-management-design.md`

## Deliberate deviation from the spec

The spec lists four shared components including `<x-admin.form-card>`. This plan
builds three — `table`, `active-badge`, `row-actions` — and writes each modal
form out directly instead.

The reason: the four forms have little in common beyond a heading and a
save/cancel row. Divisions take a name and code, sections add a division select,
positions add an item number and salary grade, designations take a title alone.
A `form-card` wrapping only the heading and the button row would earn less than
it costs in indirection. If a fifth entity arrives and the pattern proves itself,
extract it then.

## Global Constraints

- **English only.** Every comment, docblock, seed value and user-facing string is English. No Tagalog anywhere.
- **Page width.** Every admin page body is wrapped in `<x-page-container>`. Never hand-roll a `max-w-5xl mx-auto` column.
- **Route protection lives on the group**, never per controller method: `->middleware(['auth', 'role:admin'])`. Do not add `verified` — `MustVerifyEmail` is commented out on `App\Models\User`, so it guarantees nothing.
- **Non-admins get 403**, never a redirect.
- **`setActive` takes an explicit boolean**, never a toggle that flips whatever it finds.
- **Tests run on in-memory SQLite** (`phpunit.xml`), not the dev MySQL database. Feature tests need `RefreshDatabase`.
- **PHPUnit 12 removed the `@dataProvider` annotation.** Use the `#[DataProvider]` attribute.
- **Git:** this repository currently has **zero commits** and the user manages git personally. Ask the user before running the first `git commit`. If they decline, skip every commit step and leave the work uncommitted.

---

### Task 1: Roles foundation

Wires spatie into the app and creates the admin account. Nothing is protected yet — that is Task 2.

**Files:**
- Modify: `app/Models/User.php`
- Modify: `bootstrap/app.php`
- Create: `database/seeders/RoleSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Modify: `database/seeders/DemoSeeder.php`
- Test: `tests/Feature/Admin/RoleSetupTest.php`

**Interfaces:**
- Consumes: nothing
- Produces: `User::hasRole(string): bool` (from `HasRoles`); role names `admin`, `hr`, `employee` on the `web` guard; the `role` middleware alias; seeded account `admin@example.com` / `password` with the `admin` role and **no** `Employee` record.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/RoleSetupTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_three_roles_are_seeded(): void
    {
        $this->seed(RoleSeeder::class);

        $this->assertSame(
            ['admin', 'employee', 'hr'],
            Role::query()->orderBy('name')->pluck('name')->all()
        );
    }

    public function test_seeding_roles_twice_does_not_duplicate_them(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->assertSame(3, Role::query()->count());
    }

    public function test_the_demo_seeder_creates_an_admin_with_no_employee_record(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(DemoSeeder::class);

        $admin = User::where('email', 'admin@example.com')->first();

        $this->assertNotNull($admin, 'DemoSeeder did not create admin@example.com.');
        $this->assertTrue($admin->hasRole('admin'));
        $this->assertNull($admin->employee, 'The admin must not have an Employee record.');
    }

    public function test_the_ipcr_flow_accounts_do_not_get_admin(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(DemoSeeder::class);

        foreach (['test@example.com', 'sectionhead@example.com', 'divisionhead@example.com'] as $email) {
            $this->assertFalse(
                User::where('email', $email)->first()->hasRole('admin'),
                $email . ' must not be an admin.'
            );
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RoleSetupTest`
Expected: FAIL — `Class "Database\Seeders\RoleSeeder" not found`.

- [ ] **Step 3: Add HasRoles to the User model**

In `app/Models/User.php`, add the import beside the other `use` statements and the trait beside `HasFactory`:

```php
use Spatie\Permission\Traits\HasRoles;
```

```php
class User extends Authenticatable
{
    use HasFactory;
    use HasRoles;
    use Notifiable;
```

Keep the existing traits — only add `HasRoles`.

- [ ] **Step 4: Register the role middleware alias**

In `bootstrap/app.php`, replace the empty `withMiddleware` closure:

```php
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
        ]);
    })
```

Without this alias `role:admin` throws rather than denying access.

- [ ] **Step 5: Create the RoleSeeder**

Create `database/seeders/RoleSeeder.php`:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * The three roles the system recognises.
 *
 * Only `admin` grants anything in Phase 1. `hr` and `employee` exist so that a
 * later split of admin duties needs no migration - just policy changes.
 *
 * Idempotent: safe to run repeatedly.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['admin', 'hr', 'employee'] as $name) {
            Role::findOrCreate($name, 'web');
        }
    }
}
```

- [ ] **Step 6: Call RoleSeeder before DemoSeeder**

In `database/seeders/DatabaseSeeder.php`, change `run()`:

```php
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(DemoSeeder::class);
    }
```

Order matters: `DemoSeeder` assigns the `admin` role, which must exist first.

- [ ] **Step 7: Create the admin account in DemoSeeder**

In `database/seeders/DemoSeeder.php`, add this at the end of `run()`, after the existing people are created:

```php
        // --- Administrator -------------------------------------------

        // Deliberately has no Employee record. A system administrator and a
        // rated employee are different things, and keeping them apart stops
        // the admin screens from quietly depending on an employee existing.
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'System Administrator', 'password' => Hash::make('password')],
        );

        $admin->syncRoles(['admin']);
```

`User` and `Hash` are already imported in this file; confirm before adding duplicates.

- [ ] **Step 8: Run tests to verify they pass**

Run: `php artisan test --filter=RoleSetupTest`
Expected: PASS — 4 tests.

Then run the whole suite to confirm nothing regressed:
Run: `php artisan test`
Expected: PASS — all previously passing tests still pass.

- [ ] **Step 9: Commit** (ask the user first — see Global Constraints)

```bash
git add app/Models/User.php bootstrap/app.php database/seeders tests/Feature/Admin/RoleSetupTest.php
git commit -m "feat(admin): wire spatie roles and seed the admin account"
```

---

### Task 2: Admin route group and access control

Locks the security boundary before any screen exists, so no admin route can be added later without protection.

**Files:**
- Create: `app/Http/Controllers/Admin/OrganizationController.php`
- Create: `app/Http/Controllers/Admin/JobTitleController.php`
- Create: `resources/views/admin/organization/index.blade.php`
- Create: `resources/views/admin/job-titles/index.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Admin/AdminAccessTest.php`

**Interfaces:**
- Consumes: `role` middleware alias and the `admin` role from Task 1.
- Produces: named routes `admin.organization.index` (`GET admin/organization`) and `admin.job-titles.index` (`GET admin/job-titles`); both controllers return views and take no parameters.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/AdminAccessTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The security boundary for the whole admin area.
 *
 * Every admin route is listed in adminRoutes(). A new admin route added
 * without protection fails here, which is the point: protection lives on the
 * route group, and this test is what proves the group still covers everything.
 */
class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public static function adminRoutes(): array
    {
        return [
            'organization' => ['admin.organization.index'],
            'job titles'   => ['admin.job-titles.index'],
        ];
    }

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    #[DataProvider('adminRoutes')]
    public function test_a_guest_is_sent_to_login(string $routeName): void
    {
        $this->seed(RoleSeeder::class);

        $this->get(route($routeName))->assertRedirect(route('login'));
    }

    #[DataProvider('adminRoutes')]
    public function test_a_signed_in_non_admin_is_forbidden(string $routeName): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route($routeName))->assertForbidden();
    }

    #[DataProvider('adminRoutes')]
    public function test_an_admin_gets_through(string $routeName): void
    {
        $this->actingAs($this->admin())->get(route($routeName))->assertOk();
    }

    /**
     * The seeded admin has no Employee record, so every admin page must render
     * for a user whose `employee` relation is null. Asserted directly rather
     * than left to chance: IpcrController already aborts 403 when an employee
     * is missing, and it would be easy to copy that habit into an admin screen
     * without noticing.
     */
    #[DataProvider('adminRoutes')]
    public function test_an_admin_without_an_employee_record_can_still_load_the_page(string $routeName): void
    {
        $admin = $this->admin();

        $this->assertNull($admin->employee, 'This test is meaningless if the admin has an employee.');

        $this->actingAs($admin)->get(route($routeName))->assertOk();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AdminAccessTest`
Expected: FAIL — `Route [admin.organization.index] not defined.`

- [ ] **Step 3: Create the two page controllers**

Create `app/Http/Controllers/Admin/OrganizationController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Division;
use Illuminate\View\View;

/**
 * The organization tree: every division with its sections nested underneath.
 *
 * A section cannot exist without a division, so showing them together is the
 * point of the screen rather than a convenience.
 */
class OrganizationController extends Controller
{
    public function index(): View
    {
        $divisions = Division::query()
            ->with(['sections' => fn ($q) => $q->orderBy('name'), 'sections.head', 'head'])
            ->orderBy('name')
            ->get();

        return view('admin.organization.index', compact('divisions'));
    }
}
```

Create `app/Http/Controllers/Admin/JobTitleController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Designation;
use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Positions and designations on one page, as two tabs.
 *
 * They share a page because an administrator thinks of both as "job titles you
 * assign to people". They stay separate models because they mean different
 * things: a position is the single plantilla post and the source of CORE
 * functions; a designation is an extra assignment an employee may hold several
 * of, and is the source of STRATEGIC and SUPPORT functions.
 */
class JobTitleController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'designations' ? 'designations' : 'positions';

        $positions = Position::query()->orderBy('title')->get();
        $designations = Designation::query()->orderBy('title')->get();

        return view('admin.job-titles.index', compact('tab', 'positions', 'designations'));
    }
}
```

- [ ] **Step 4: Create the two placeholder views**

Create `resources/views/admin/organization/index.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Organization') }}</h2>
    </x-slot>

    <x-page-container class="space-y-6">
        <p class="text-sm text-gray-600">{{ $divisions->count() }} division(s).</p>
    </x-page-container>
</x-app-layout>
```

Create `resources/views/admin/job-titles/index.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Job Titles') }}</h2>
    </x-slot>

    <x-page-container class="space-y-6">
        <p class="text-sm text-gray-600">
            {{ $positions->count() }} position(s), {{ $designations->count() }} designation(s). Tab: {{ $tab }}
        </p>
    </x-page-container>
</x-app-layout>
```

These are replaced in Tasks 5 and 8. They exist now so the access test has something real to hit.

- [ ] **Step 5: Add the admin route group**

In `routes/web.php`, add these imports at the top beside the existing controller imports:

```php
use App\Http\Controllers\Admin\JobTitleController;
use App\Http\Controllers\Admin\OrganizationController;
```

Then add this group **after** the existing `Route::middleware('auth')->group(...)` block and before `require __DIR__ . '/auth.php';`:

```php
/*
 * Admin area.
 *
 * Protection lives here on the group, not on individual controllers: a new
 * admin route is then protected by default rather than one forgotten line away
 * from being open.
 *
 * `verified` is deliberately absent. MustVerifyEmail is commented out on the
 * User model, so email verification is not enforced anywhere in this app and
 * including it here would imply a guarantee that does not exist.
 */
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'role:admin'])
    ->group(function () {
        Route::get('/organization', [OrganizationController::class, 'index'])->name('organization.index');
        Route::get('/job-titles', [JobTitleController::class, 'index'])->name('job-titles.index');
    });
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=AdminAccessTest`
Expected: PASS — 6 tests (3 cases × 2 routes).

- [ ] **Step 7: Verify the 403 by hand**

Run: `php artisan route:list --path=admin`
Expected: both routes listed with middleware `web`, `auth`, `role:admin`.

- [ ] **Step 8: Commit** (ask the user first)

```bash
git add app/Http/Controllers/Admin resources/views/admin routes/web.php tests/Feature/Admin/AdminAccessTest.php
git commit -m "feat(admin): add protected admin route group with two pages"
```

---

### Task 3: Administration navigation group

**Files:**
- Modify: `resources/views/layouts/sidebar.blade.php`
- Test: `tests/Feature/Admin/AdminNavigationTest.php`

**Interfaces:**
- Consumes: `admin.organization.index`, `admin.job-titles.index` from Task 2; `User::hasRole()` from Task 1.
- Produces: nothing other tasks depend on.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/AdminNavigationTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_sees_the_administration_group(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Administration');
        $response->assertSee('Organization');
        $response->assertSee('Job Titles');
    }

    public function test_a_non_admin_sees_no_administration_group(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertDontSee('Administration');
        $response->assertDontSee('Job Titles');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AdminNavigationTest`
Expected: FAIL on the first test — the page does not contain "Administration".

- [ ] **Step 3: Add the nav group**

In `resources/views/layouts/sidebar.blade.php`, inside `<nav class="flex-1 ...">`, after the existing "My IPCRs" `<x-sidebar-link>` and before `</nav>`:

```blade
        @if ($user?->hasRole('admin'))
            {{-- Administration. Hidden entirely from non-admins: the routes
                 return 403 anyway, but there is no reason to advertise them. --}}
            <p class="px-3 pb-1 pt-5 font-data text-[0.625rem] uppercase tracking-[0.18em] text-nav-300"
                :class="collapsed ? 'lg:hidden' : ''">
                Administration
            </p>

            <x-sidebar-link :href="route('admin.organization.index')"
                :active="request()->routeIs('admin.organization.*')">
                <x-slot:icon>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 4v4m0 0H7.5A1.5 1.5 0 0 0 6 9.5V12m6-4h4.5A1.5 1.5 0 0 1 18 9.5V12M6 12v3m6-3v3m6-3v3M4 15h4v5H4v-5Zm6 0h4v5h-4v-5Zm6 0h4v5h-4v-5Z" />
                    </svg>
                </x-slot:icon>
                Organization
            </x-sidebar-link>

            <x-sidebar-link :href="route('admin.job-titles.index')"
                :active="request()->routeIs('admin.job-titles.*')">
                <x-slot:icon>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 6.5V5.5A1.5 1.5 0 0 1 10.5 4h3A1.5 1.5 0 0 1 15 5.5v1M4.5 6.5h15A1.5 1.5 0 0 1 21 8v10a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 18V8a1.5 1.5 0 0 1 1.5-1.5ZM3 12h18" />
                    </svg>
                </x-slot:icon>
                Job Titles
            </x-sidebar-link>
        @endif
```

`$user` is already defined in the `@php` block at the top of this file.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=AdminNavigationTest`
Expected: PASS — 2 tests.

- [ ] **Step 5: Prove the test has teeth**

Temporarily change `@if ($user?->hasRole('admin'))` to `@if (true)`, run `php artisan view:clear` then `php artisan test --filter=AdminNavigationTest`.
Expected: the non-admin test FAILS. Restore the condition and re-run; both pass again.

This matters because a Blade `@if` that always renders is invisible in a passing suite unless one test asserts the negative.

- [ ] **Step 6: Rebuild assets and commit** (ask the user first)

```bash
npm run build
git add resources/views/layouts/sidebar.blade.php tests/Feature/Admin/AdminNavigationTest.php
git commit -m "feat(admin): add admin-only Administration nav group"
```

---

### Task 4: OrgDeletionGuard

A pure service with no HTTP surface, so it is unit tested on its own before any screen calls it.

**Files:**
- Create: `app/Support/DeletionReport.php`
- Create: `app/Services/OrgDeletionGuard.php`
- Create: `database/factories/DivisionFactory.php`
- Create: `database/factories/SectionFactory.php`
- Create: `database/factories/PositionFactory.php`
- Create: `database/factories/DesignationFactory.php`
- Modify: `app/Models/Division.php`, `app/Models/Section.php`, `app/Models/Position.php`, `app/Models/Designation.php` (add `HasFactory`)
- Test: `tests/Feature/Admin/OrgDeletionGuardTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `App\Support\DeletionReport` — `readonly` with `public bool $deletable` and `public array $blockers` (`array<string,int>`), plus `public function message(): string`.
  - `App\Services\OrgDeletionGuard::for(Model $record): DeletionReport` — accepts `Division`, `Section`, `Position`, `Designation`; throws `InvalidArgumentException` for anything else.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/OrgDeletionGuardTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\Designation;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Section;
use App\Services\OrgDeletionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrgDeletionGuardTest extends TestCase
{
    use RefreshDatabase;

    private OrgDeletionGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new OrgDeletionGuard();
    }

    public function test_an_unreferenced_division_is_deletable(): void
    {
        $report = $this->guard->for(Division::factory()->create());

        $this->assertTrue($report->deletable);
        $this->assertSame([], $report->blockers);
    }

    public function test_a_division_with_sections_is_blocked(): void
    {
        $division = Division::factory()->create();
        Section::factory()->count(3)->create(['division_id' => $division->id]);

        $report = $this->guard->for($division);

        $this->assertFalse($report->deletable);
        $this->assertSame(['sections' => 3], $report->blockers);
    }

    public function test_a_division_counts_sections_and_employees_separately(): void
    {
        $division = Division::factory()->create();
        Section::factory()->create(['division_id' => $division->id]);
        Employee::factory()->count(2)->create(['division_id' => $division->id]);

        $report = $this->guard->for($division);

        $this->assertSame(['sections' => 1, 'employees' => 2], $report->blockers);
    }

    public function test_a_section_is_blocked_by_employees(): void
    {
        $section = Section::factory()->create();
        Employee::factory()->create(['section_id' => $section->id]);

        $report = $this->guard->for($section);

        $this->assertFalse($report->deletable);
        $this->assertSame(['employees' => 1], $report->blockers);
    }

    public function test_a_position_is_blocked_by_employees(): void
    {
        $position = Position::factory()->create();
        Employee::factory()->create(['position_id' => $position->id]);

        $report = $this->guard->for($position);

        $this->assertSame(['employees' => 1], $report->blockers);
    }

    public function test_an_unreferenced_designation_is_deletable(): void
    {
        $this->assertTrue($this->guard->for(Designation::factory()->create())->deletable);
    }

    public function test_the_message_names_what_is_in_the_way(): void
    {
        $division = Division::factory()->create();
        Section::factory()->count(2)->create(['division_id' => $division->id]);

        $message = $this->guard->for($division)->message();

        $this->assertStringContainsString('2 sections', $message);
        $this->assertStringContainsString('Deactivate it instead', $message);
    }

    public function test_an_unsupported_model_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->guard->for(Employee::factory()->create());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=OrgDeletionGuardTest`
Expected: FAIL — `Class "App\Services\OrgDeletionGuard" not found`.

- [ ] **Step 3: Add HasFactory to the four models**

For each of `app/Models/Division.php`, `Section.php`, `Position.php`, `Designation.php`, add the import beside the other `use` statements and the trait as the first line of the class body:

```php
use Illuminate\Database\Eloquent\Factories\HasFactory;
```

```php
    use HasFactory;
```

`Employee` already has `HasFactory`; do not add it twice.

- [ ] **Step 4: Create the four factories**

Create `database/factories/DivisionFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Division;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Division> */
class DivisionFactory extends Factory
{
    protected $model = Division::class;

    public function definition(): array
    {
        return [
            'name'      => $this->faker->unique()->words(2, true) . ' Division',
            'code'      => strtoupper($this->faker->unique()->lexify('???')),
            'is_active' => true,
        ];
    }
}
```

Create `database/factories/SectionFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Division;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Section> */
class SectionFactory extends Factory
{
    protected $model = Section::class;

    public function definition(): array
    {
        return [
            'division_id' => Division::factory(),
            'name'        => $this->faker->unique()->words(2, true) . ' Section',
            'code'        => strtoupper($this->faker->unique()->lexify('????')),
            'is_active'   => true,
        ];
    }
}
```

Create `database/factories/PositionFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Position> */
class PositionFactory extends Factory
{
    protected $model = Position::class;

    public function definition(): array
    {
        return [
            'title'        => $this->faker->unique()->jobTitle(),
            'item_number'  => 'ITEM-' . $this->faker->unique()->numerify('####'),
            'salary_grade' => $this->faker->numberBetween(1, 33),
            'is_active'    => true,
        ];
    }
}
```

Create `database/factories/DesignationFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Designation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Designation> */
class DesignationFactory extends Factory
{
    protected $model = Designation::class;

    public function definition(): array
    {
        return [
            'title'     => 'OIC - ' . $this->faker->unique()->jobTitle(),
            'is_active' => true,
        ];
    }
}
```

- [ ] **Step 5: Create DeletionReport**

Create `app/Support/DeletionReport.php`:

```php
<?php

namespace App\Support;

/**
 * What is standing in the way of deleting one organizational record.
 *
 * `blockers` maps a human label to a count, e.g. ['sections' => 3]. An empty
 * map means nothing references the record and it can be deleted.
 */
final readonly class DeletionReport
{
    /** @param array<string,int> $blockers */
    public function __construct(
        public bool $deletable,
        public array $blockers = [],
    ) {}

    /** The sentence shown to the administrator when deletion is refused. */
    public function message(): string
    {
        if ($this->deletable) {
            return 'Nothing references this record.';
        }

        $parts = [];

        foreach ($this->blockers as $label => $count) {
            $parts[] = $count . ' ' . ($count === 1 ? rtrim($label, 's') : $label);
        }

        $last = array_pop($parts);
        $list = $parts === [] ? $last : implode(', ', $parts) . ' and ' . $last;

        return "Cannot delete — {$list} reference this. Deactivate it instead.";
    }
}
```

- [ ] **Step 6: Create OrgDeletionGuard**

Create `app/Services/OrgDeletionGuard.php`:

```php
<?php

namespace App\Services;

use App\Models\Designation;
use App\Models\Division;
use App\Models\Position;
use App\Models\Section;
use App\Support\DeletionReport;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Answers one question about an organizational record: what still references it?
 *
 * Deactivating is the normal way to retire a record, and the schema agrees -
 * every one of these tables carries `is_active` and their foreign keys use
 * restrictOnDelete. Deleting is the exception, allowed only when nothing points
 * at the record. Without this check the user would meet a raw foreign-key error
 * instead of a sentence telling them what is in the way.
 */
class OrgDeletionGuard
{
    public function for(Model $record): DeletionReport
    {
        $blockers = array_filter(match (true) {
            $record instanceof Division => [
                'sections'  => $record->sections()->count(),
                'employees' => $record->employees()->count(),
            ],
            $record instanceof Section => [
                'employees' => $record->employees()->count(),
            ],
            $record instanceof Position => [
                'job functions' => $record->jobFunctions()->count(),
                'employees'     => $record->employees()->count(),
            ],
            $record instanceof Designation => [
                'job functions' => $record->jobFunctions()->count(),
                'employees'     => $record->employees()->count(),
            ],
            default => throw new InvalidArgumentException(
                'OrgDeletionGuard does not handle ' . $record::class . '.'
            ),
        }, fn (int $count): bool => $count > 0);

        return new DeletionReport(deletable: $blockers === [], blockers: $blockers);
    }
}
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test --filter=OrgDeletionGuardTest`
Expected: PASS — 8 tests.

- [ ] **Step 8: Commit** (ask the user first)

```bash
git add app/Services/OrgDeletionGuard.php app/Support/DeletionReport.php app/Models database/factories tests/Feature/Admin/OrgDeletionGuardTest.php
git commit -m "feat(admin): add OrgDeletionGuard with per-model blocker counts"
```

---

### Task 5: Divisions CRUD and the shared admin components

The first real screen. The `x-admin.*` components are born here because this is the first task that needs them.

**Files:**
- Create: `resources/views/components/admin/table.blade.php`
- Create: `resources/views/components/admin/active-badge.blade.php`
- Create: `resources/views/components/admin/row-actions.blade.php`
- Create: `app/Http/Controllers/Admin/DivisionController.php`
- Create: `app/Http/Requests/Admin/StoreDivisionRequest.php`
- Create: `app/Http/Requests/Admin/UpdateDivisionRequest.php`
- Modify: `routes/web.php`
- Rewrite: `resources/views/admin/organization/index.blade.php`
- Test: `tests/Feature/Admin/DivisionManagementTest.php`

**Interfaces:**
- Consumes: `OrgDeletionGuard::for()` and `DeletionReport` from Task 4; the admin route group from Task 2.
- Produces: named routes `admin.divisions.store` (POST `admin/divisions`), `admin.divisions.update` (PUT `admin/divisions/{division}`), `admin.divisions.active` (PATCH `admin/divisions/{division}/active`), `admin.divisions.destroy` (DELETE `admin/divisions/{division}`). Components `<x-admin.table>` (slots: `head`, default body), `<x-admin.active-badge :active="bool">`, `<x-admin.row-actions>`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/DivisionManagementTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\Division;
use App\Models\Employee;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DivisionManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_an_admin_can_create_a_division(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.divisions.store'), ['name' => 'Medical Division', 'code' => 'MED'])
            ->assertRedirect(route('admin.organization.index'));

        $this->assertDatabaseHas('divisions', [
            'name' => 'Medical Division', 'code' => 'MED', 'is_active' => true,
        ]);
    }

    public function test_a_division_needs_a_name(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.divisions.store'), ['name' => '', 'code' => 'MED'])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, Division::count());
    }

    public function test_a_division_code_must_be_unique(): void
    {
        Division::factory()->create(['code' => 'MED']);

        $this->actingAs($this->admin())
            ->post(route('admin.divisions.store'), ['name' => 'Another', 'code' => 'MED'])
            ->assertSessionHasErrors('code');
    }

    public function test_a_division_code_may_be_left_blank(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.divisions.store'), ['name' => 'No Code Division', 'code' => null])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('divisions', ['name' => 'No Code Division', 'code' => null]);
    }

    public function test_an_admin_can_rename_a_division(): void
    {
        $division = Division::factory()->create(['name' => 'Old']);

        $this->actingAs($this->admin())
            ->put(route('admin.divisions.update', $division), ['name' => 'New', 'code' => $division->code])
            ->assertRedirect(route('admin.organization.index'));

        $this->assertSame('New', $division->fresh()->name);
    }

    public function test_updating_a_division_ignores_its_own_code_when_checking_uniqueness(): void
    {
        $division = Division::factory()->create(['code' => 'MED']);

        $this->actingAs($this->admin())
            ->put(route('admin.divisions.update', $division), ['name' => 'Renamed', 'code' => 'MED'])
            ->assertSessionHasNoErrors();
    }

    public function test_an_admin_can_deactivate_and_reactivate_a_division(): void
    {
        $division = Division::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin())
            ->patch(route('admin.divisions.active', $division), ['active' => false]);
        $this->assertFalse($division->fresh()->is_active);

        $this->actingAs($this->admin())
            ->patch(route('admin.divisions.active', $division), ['active' => true]);
        $this->assertTrue($division->fresh()->is_active);
    }

    public function test_an_unreferenced_division_can_be_deleted(): void
    {
        $division = Division::factory()->create();

        $this->actingAs($this->admin())
            ->delete(route('admin.divisions.destroy', $division))
            ->assertRedirect(route('admin.organization.index'));

        $this->assertDatabaseMissing('divisions', ['id' => $division->id]);
    }

    public function test_a_referenced_division_survives_a_delete_attempt(): void
    {
        $division = Division::factory()->create();
        Section::factory()->create(['division_id' => $division->id]);

        $this->actingAs($this->admin())->delete(route('admin.divisions.destroy', $division));

        $this->assertDatabaseHas('divisions', ['id' => $division->id]);
        $this->assertStringContainsString('Cannot delete', (string) session('error'));
    }

    public function test_the_organization_page_lists_divisions(): void
    {
        Division::factory()->create(['name' => 'Medical Division']);

        $this->actingAs($this->admin())
            ->get(route('admin.organization.index'))
            ->assertOk()
            ->assertSee('Medical Division');
    }

    public function test_a_non_admin_cannot_create_a_division(): void
    {
        $this->seed(RoleSeeder::class);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.divisions.store'), ['name' => 'Sneaky'])
            ->assertForbidden();

        $this->assertSame(0, Division::count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=DivisionManagementTest`
Expected: FAIL — `Route [admin.divisions.store] not defined.`

- [ ] **Step 3: Create the form requests**

Create `app/Http/Requests/Admin/StoreDivisionRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreDivisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The admin route group already enforces role:admin.
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20', 'unique:divisions,code'],
            'division_head_employee_id' => ['nullable', 'exists:employees,id'],
        ];
    }
}
```

Create `app/Http/Requests/Admin/UpdateDivisionRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDivisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // The record keeps its own code: without ignore() every save of an
        // unchanged form would fail its own uniqueness check.
        $divisionId = $this->route('division')->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20', Rule::unique('divisions', 'code')->ignore($divisionId)],
            'division_head_employee_id' => ['nullable', 'exists:employees,id'],
        ];
    }
}
```

- [ ] **Step 4: Create the DivisionController**

Create `app/Http/Controllers/Admin/DivisionController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDivisionRequest;
use App\Http\Requests\Admin\UpdateDivisionRequest;
use App\Models\Division;
use App\Services\OrgDeletionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DivisionController extends Controller
{
    public function __construct(private readonly OrgDeletionGuard $guard) {}

    public function store(StoreDivisionRequest $request): RedirectResponse
    {
        $division = Division::create($request->validated() + ['is_active' => true]);

        return redirect()->route('admin.organization.index')
            ->with('status', "Created division \"{$division->name}\".");
    }

    public function update(UpdateDivisionRequest $request, Division $division): RedirectResponse
    {
        $division->update($request->validated());

        return redirect()->route('admin.organization.index')
            ->with('status', "Updated division \"{$division->name}\".");
    }

    public function setActive(Request $request, Division $division): RedirectResponse
    {
        // An explicit value, not a toggle: two tabs disagreeing about the
        // current state would otherwise flip it the wrong way.
        $validated = $request->validate(['active' => ['required', 'boolean']]);

        $division->update(['is_active' => $validated['active']]);

        return redirect()->route('admin.organization.index')->with(
            'status',
            ($validated['active'] ? 'Activated' : 'Deactivated') . " division \"{$division->name}\"."
        );
    }

    public function destroy(Division $division): RedirectResponse
    {
        // Re-checked here, not just in the view: a stale tab could otherwise
        // delete a record that gained a reference in the meantime.
        $report = $this->guard->for($division);

        if (! $report->deletable) {
            return redirect()->route('admin.organization.index')->with('error', $report->message());
        }

        $name = $division->name;
        $division->delete();

        return redirect()->route('admin.organization.index')
            ->with('status', "Deleted division \"{$name}\".");
    }
}
```

- [ ] **Step 5: Add the routes**

In `routes/web.php`, add the import:

```php
use App\Http\Controllers\Admin\DivisionController;
```

Inside the existing admin group, after the two page routes:

```php
        Route::post('/divisions', [DivisionController::class, 'store'])->name('divisions.store');
        Route::put('/divisions/{division}', [DivisionController::class, 'update'])->name('divisions.update');
        Route::patch('/divisions/{division}/active', [DivisionController::class, 'setActive'])->name('divisions.active');
        Route::delete('/divisions/{division}', [DivisionController::class, 'destroy'])->name('divisions.destroy');
```

- [ ] **Step 6: Create the shared components**

Create `resources/views/components/admin/active-badge.blade.php`:

```blade
@props(['active' => true])

<span
    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $active ? 'bg-emerald-100 text-emerald-800 ring-emerald-500/20' : 'bg-gray-100 text-gray-600 ring-gray-500/20' }}">
    {{ $active ? 'Active' : 'Inactive' }}
</span>
```

Create `resources/views/components/admin/table.blade.php`:

```blade
@props(['head' => null, 'empty' => 'Nothing here yet.'])

{{-- Shared table shell so every admin list has the same header treatment,
     borders and empty state. Wrapped in an overflow container because a wide
     table must scroll inside itself rather than push the page sideways. --}}
<div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-950/5">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            @isset($head)
                <thead class="bg-gray-50">
                    <tr>{{ $head }}</tr>
                </thead>
            @endisset
            <tbody class="divide-y divide-gray-200 bg-white">
                {{ $slot }}
            </tbody>
        </table>
    </div>
</div>
```

Create `resources/views/components/admin/row-actions.blade.php`:

```blade
@props(['record', 'report', 'activeRoute', 'destroyRoute'])

{{-- Deactivate / activate is the normal retirement path; delete is the
     exception and is only offered when nothing references the record. --}}
<div class="flex items-center justify-end gap-3">
    {{ $slot }}

    <form method="POST" action="{{ $activeRoute }}">
        @csrf
        @method('PATCH')
        <input type="hidden" name="active" value="{{ $record->is_active ? 0 : 1 }}">
        <button type="submit" class="text-sm font-medium text-gray-700 hover:underline">
            {{ $record->is_active ? 'Deactivate' : 'Activate' }}
        </button>
    </form>

    @if ($report->deletable)
        <form method="POST" action="{{ $destroyRoute }}"
            onsubmit="return confirm('Delete this permanently? This cannot be undone.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-sm font-medium text-red-600 hover:underline">Delete</button>
        </form>
    @else
        <span class="cursor-not-allowed text-sm font-medium text-gray-300" title="{{ $report->message() }}">Delete</span>
    @endif
</div>
```

- [ ] **Step 7: Rewrite the organization page**

Replace `resources/views/admin/organization/index.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Organization') }}</h2>
            <button type="button" x-data x-on:click="$dispatch('open-modal', 'create-division')"
                class="inline-flex items-center rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-nav-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-seal focus-visible:ring-offset-2">
                + New Division
            </button>
        </div>
    </x-slot>

    <x-page-container class="space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-emerald-50 p-4 text-sm text-emerald-800 ring-1 ring-emerald-500/20">
                {{ session('status') }}</div>
        @endif

        @if (session('error'))
            <div class="rounded-md bg-red-50 p-4 text-sm text-red-800 ring-1 ring-red-500/20">{{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-md bg-red-50 p-4 text-sm text-red-800 ring-1 ring-red-500/20">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <x-admin.table>
            <x-slot:head>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Division</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Code</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                <th class="px-6 py-3"></th>
            </x-slot:head>

            @forelse ($divisions as $division)
                <tr>
                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $division->name }}</td>
                    <td class="px-6 py-4 font-data text-sm text-gray-600">{{ $division->code ?? '—' }}</td>
                    <td class="px-6 py-4"><x-admin.active-badge :active="$division->is_active" /></td>
                    <td class="px-6 py-4">
                        <x-admin.row-actions :record="$division" :report="$reports[$division->id]"
                            :active-route="route('admin.divisions.active', $division)"
                            :destroy-route="route('admin.divisions.destroy', $division)" />
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">No divisions yet.</td>
                </tr>
            @endforelse
        </x-admin.table>

        <x-modal name="create-division" focusable max-width="lg">
            <form method="POST" action="{{ route('admin.divisions.store') }}" class="space-y-4 p-6">
                @csrf
                <h2 class="text-lg font-semibold text-gray-900">New division</h2>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700">Name</span>
                    <input type="text" name="name" required class="w-full rounded-md border-gray-300 text-sm">
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700">Code <span
                            class="text-gray-400">(optional)</span></span>
                    <input type="text" name="code" maxlength="20" class="w-full rounded-md border-gray-300 text-sm">
                </label>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" x-on:click="$dispatch('close-modal', 'create-division')"
                        class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Cancel</button>
                    <button type="submit"
                        class="rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white hover:bg-nav-800">Create
                        division</button>
                </div>
            </form>
        </x-modal>
    </x-page-container>
</x-app-layout>
```

- [ ] **Step 8: Pass the deletion reports from the controller**

In `app/Http/Controllers/Admin/OrganizationController.php`, inject the guard and build a report per division:

```php
    public function __construct(private readonly OrgDeletionGuard $guard) {}

    public function index(): View
    {
        $divisions = Division::query()
            ->with(['sections' => fn ($q) => $q->orderBy('name'), 'sections.head', 'head'])
            ->orderBy('name')
            ->get();

        $reports = $divisions->mapWithKeys(
            fn (Division $division) => [$division->id => $this->guard->for($division)]
        );

        return view('admin.organization.index', compact('divisions', 'reports'));
    }
```

Add `use App\Services\OrgDeletionGuard;` to the imports.

- [ ] **Step 9: Run tests to verify they pass**

Run: `php artisan test --filter=DivisionManagementTest`
Expected: PASS — 11 tests.

Run: `php artisan test`
Expected: PASS — the whole suite.

- [ ] **Step 10: Rebuild assets and commit** (ask the user first)

```bash
npm run build
git add app resources routes tests
git commit -m "feat(admin): manage divisions with shared admin table components"
```

---

### Task 6: Sections nested under divisions

**Files:**
- Create: `app/Http/Controllers/Admin/SectionController.php`
- Create: `app/Http/Requests/Admin/StoreSectionRequest.php`
- Create: `app/Http/Requests/Admin/UpdateSectionRequest.php`
- Modify: `routes/web.php`
- Modify: `resources/views/admin/organization/index.blade.php`
- Modify: `app/Http/Controllers/Admin/OrganizationController.php`
- Test: `tests/Feature/Admin/SectionManagementTest.php`

**Interfaces:**
- Consumes: everything from Task 5.
- Produces: named routes `admin.sections.store`, `admin.sections.update`, `admin.sections.active`, `admin.sections.destroy`, with the same shapes as their division counterparts. `OrganizationController@index` additionally passes `$sectionReports` keyed by section id.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/SectionManagementTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\Division;
use App\Models\Employee;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectionManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_an_admin_can_create_a_section_inside_a_division(): void
    {
        $division = Division::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.sections.store'), [
                'division_id' => $division->id,
                'name'        => 'Nursing Section',
                'code'        => 'NUR',
            ])
            ->assertRedirect(route('admin.organization.index'));

        $this->assertDatabaseHas('sections', [
            'division_id' => $division->id, 'name' => 'Nursing Section', 'is_active' => true,
        ]);
    }

    public function test_a_section_must_belong_to_an_existing_division(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.sections.store'), ['division_id' => 9999, 'name' => 'Orphan'])
            ->assertSessionHasErrors('division_id');

        $this->assertSame(0, Section::count());
    }

    public function test_a_section_needs_a_name(): void
    {
        $division = Division::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.sections.store'), ['division_id' => $division->id, 'name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_a_section_code_must_be_unique(): void
    {
        $division = Division::factory()->create();
        Section::factory()->create(['code' => 'NUR']);

        $this->actingAs($this->admin())
            ->post(route('admin.sections.store'), [
                'division_id' => $division->id, 'name' => 'Another', 'code' => 'NUR',
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_an_admin_can_move_a_section_to_another_division(): void
    {
        $section = Section::factory()->create();
        $target = Division::factory()->create();

        $this->actingAs($this->admin())
            ->put(route('admin.sections.update', $section), [
                'division_id' => $target->id, 'name' => $section->name, 'code' => $section->code,
            ])
            ->assertRedirect(route('admin.organization.index'));

        $this->assertSame($target->id, $section->fresh()->division_id);
    }

    public function test_an_admin_can_deactivate_and_reactivate_a_section(): void
    {
        $section = Section::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin())->patch(route('admin.sections.active', $section), ['active' => false]);
        $this->assertFalse($section->fresh()->is_active);

        $this->actingAs($this->admin())->patch(route('admin.sections.active', $section), ['active' => true]);
        $this->assertTrue($section->fresh()->is_active);
    }

    public function test_an_unreferenced_section_can_be_deleted(): void
    {
        $section = Section::factory()->create();

        $this->actingAs($this->admin())->delete(route('admin.sections.destroy', $section));

        $this->assertDatabaseMissing('sections', ['id' => $section->id]);
    }

    public function test_a_section_with_employees_survives_a_delete_attempt(): void
    {
        $section = Section::factory()->create();
        Employee::factory()->create(['section_id' => $section->id]);

        $this->actingAs($this->admin())->delete(route('admin.sections.destroy', $section));

        $this->assertDatabaseHas('sections', ['id' => $section->id]);
        $this->assertStringContainsString('Cannot delete', (string) session('error'));
    }

    public function test_the_organization_page_shows_sections_under_their_division(): void
    {
        $division = Division::factory()->create(['name' => 'Medical Division']);
        Section::factory()->create(['division_id' => $division->id, 'name' => 'Nursing Section']);

        $this->actingAs($this->admin())
            ->get(route('admin.organization.index'))
            ->assertOk()
            ->assertSeeInOrder(['Medical Division', 'Nursing Section']);
    }

    public function test_a_non_admin_cannot_create_a_section(): void
    {
        $this->seed(RoleSeeder::class);
        $division = Division::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.sections.store'), ['division_id' => $division->id, 'name' => 'Sneaky'])
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SectionManagementTest`
Expected: FAIL — `Route [admin.sections.store] not defined.`

- [ ] **Step 3: Create the form requests**

Create `app/Http/Requests/Admin/StoreSectionRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'division_id' => ['required', 'exists:divisions,id'],
            'name'        => ['required', 'string', 'max:255'],
            'code'        => ['nullable', 'string', 'max:20', 'unique:sections,code'],
            'section_head_employee_id' => ['nullable', 'exists:employees,id'],
        ];
    }
}
```

Create `app/Http/Requests/Admin/UpdateSectionRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sectionId = $this->route('section')->id;

        return [
            'division_id' => ['required', 'exists:divisions,id'],
            'name'        => ['required', 'string', 'max:255'],
            'code'        => ['nullable', 'string', 'max:20', Rule::unique('sections', 'code')->ignore($sectionId)],
            'section_head_employee_id' => ['nullable', 'exists:employees,id'],
        ];
    }
}
```

- [ ] **Step 4: Create the SectionController**

Create `app/Http/Controllers/Admin/SectionController.php` — same shape as `DivisionController`, with `Section` in place of `Division`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSectionRequest;
use App\Http\Requests\Admin\UpdateSectionRequest;
use App\Models\Section;
use App\Services\OrgDeletionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    public function __construct(private readonly OrgDeletionGuard $guard) {}

    public function store(StoreSectionRequest $request): RedirectResponse
    {
        $section = Section::create($request->validated() + ['is_active' => true]);

        return redirect()->route('admin.organization.index')
            ->with('status', "Created section \"{$section->name}\".");
    }

    public function update(UpdateSectionRequest $request, Section $section): RedirectResponse
    {
        $section->update($request->validated());

        return redirect()->route('admin.organization.index')
            ->with('status', "Updated section \"{$section->name}\".");
    }

    public function setActive(Request $request, Section $section): RedirectResponse
    {
        $validated = $request->validate(['active' => ['required', 'boolean']]);

        $section->update(['is_active' => $validated['active']]);

        return redirect()->route('admin.organization.index')->with(
            'status',
            ($validated['active'] ? 'Activated' : 'Deactivated') . " section \"{$section->name}\"."
        );
    }

    public function destroy(Section $section): RedirectResponse
    {
        $report = $this->guard->for($section);

        if (! $report->deletable) {
            return redirect()->route('admin.organization.index')->with('error', $report->message());
        }

        $name = $section->name;
        $section->delete();

        return redirect()->route('admin.organization.index')
            ->with('status', "Deleted section \"{$name}\".");
    }
}
```

- [ ] **Step 5: Add the routes**

Add the import `use App\Http\Controllers\Admin\SectionController;` and, inside the admin group:

```php
        Route::post('/sections', [SectionController::class, 'store'])->name('sections.store');
        Route::put('/sections/{section}', [SectionController::class, 'update'])->name('sections.update');
        Route::patch('/sections/{section}/active', [SectionController::class, 'setActive'])->name('sections.active');
        Route::delete('/sections/{section}', [SectionController::class, 'destroy'])->name('sections.destroy');
```

- [ ] **Step 6: Add section reports to the controller**

In `OrganizationController@index`, add after `$reports`:

```php
        $sectionReports = $divisions
            ->flatMap->sections
            ->mapWithKeys(fn ($section) => [$section->id => $this->guard->for($section)]);

        return view('admin.organization.index', compact('divisions', 'reports', 'sectionReports'));
```

- [ ] **Step 7: Nest sections in the view**

In `resources/views/admin/organization/index.blade.php`, inside the `@forelse ($divisions as $division)` loop, add a second `<tr>` after the division row:

```blade
                @foreach ($division->sections as $section)
                    <tr class="bg-gray-50/50">
                        <td class="py-3 pl-12 pr-6 text-sm text-gray-700">
                            <span class="text-gray-400">└</span> {{ $section->name }}
                        </td>
                        <td class="px-6 py-3 font-data text-sm text-gray-600">{{ $section->code ?? '—' }}</td>
                        <td class="px-6 py-3"><x-admin.active-badge :active="$section->is_active" /></td>
                        <td class="px-6 py-3">
                            <x-admin.row-actions :record="$section" :report="$sectionReports[$section->id]"
                                :active-route="route('admin.sections.active', $section)"
                                :destroy-route="route('admin.sections.destroy', $section)" />
                        </td>
                    </tr>
                @endforeach
```

Then add a "New Section" modal beside the division one, with a `division_id` select listing `$divisions`:

```blade
        <x-modal name="create-section" focusable max-width="lg">
            <form method="POST" action="{{ route('admin.sections.store') }}" class="space-y-4 p-6">
                @csrf
                <h2 class="text-lg font-semibold text-gray-900">New section</h2>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700">Division</span>
                    <select name="division_id" required class="w-full rounded-md border-gray-300 text-sm">
                        <option value="">Select division…</option>
                        @foreach ($divisions as $division)
                            <option value="{{ $division->id }}">{{ $division->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700">Name</span>
                    <input type="text" name="name" required class="w-full rounded-md border-gray-300 text-sm">
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700">Code <span
                            class="text-gray-400">(optional)</span></span>
                    <input type="text" name="code" maxlength="20" class="w-full rounded-md border-gray-300 text-sm">
                </label>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" x-on:click="$dispatch('close-modal', 'create-section')"
                        class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Cancel</button>
                    <button type="submit"
                        class="rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white hover:bg-nav-800">Create
                        section</button>
                </div>
            </form>
        </x-modal>
```

And a "+ New Section" trigger button in the header beside "+ New Division":

```blade
            <button type="button" x-data x-on:click="$dispatch('open-modal', 'create-section')"
                class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 transition-colors hover:bg-gray-50">
                + New Section
            </button>
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `php artisan test --filter=SectionManagementTest`
Expected: PASS — 10 tests.

Run: `php artisan test`
Expected: PASS — the whole suite.

- [ ] **Step 9: Rebuild assets and commit** (ask the user first)

```bash
npm run build
git add app resources routes tests
git commit -m "feat(admin): manage sections nested under their division"
```

---

### Task 7: Head assignments

The reason the Organization screen exists. `IpcrRoutingService` reads these two columns, and until they are set **no employee can submit an IPCR at all**.

**Files:**
- Modify: `app/Http/Controllers/Admin/OrganizationController.php`
- Modify: `resources/views/admin/organization/index.blade.php`
- Test: `tests/Feature/Admin/HeadAssignmentTest.php`

**Interfaces:**
- Consumes: `admin.divisions.update` and `admin.sections.update` from Tasks 5 and 6, whose form requests already accept `division_head_employee_id` and `section_head_employee_id`.
- Produces: `OrganizationController@index` additionally passes `$employees` (active employees, ordered by last name) for the head selects.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/HeadAssignmentTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\Division;
use App\Models\Employee;
use App\Models\Section;
use App\Models\User;
use App\Services\IpcrRoutingService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeadAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_an_admin_can_assign_a_division_head(): void
    {
        $division = Division::factory()->create();
        $head = Employee::factory()->create();

        $this->actingAs($this->admin())->put(route('admin.divisions.update', $division), [
            'name' => $division->name,
            'code' => $division->code,
            'division_head_employee_id' => $head->id,
        ]);

        $this->assertSame($head->id, $division->fresh()->division_head_employee_id);
    }

    public function test_an_admin_can_assign_a_section_head(): void
    {
        $section = Section::factory()->create();
        $head = Employee::factory()->create();

        $this->actingAs($this->admin())->put(route('admin.sections.update', $section), [
            'division_id' => $section->division_id,
            'name'        => $section->name,
            'code'        => $section->code,
            'section_head_employee_id' => $head->id,
        ]);

        $this->assertSame($head->id, $section->fresh()->section_head_employee_id);
    }

    public function test_a_head_must_be_an_existing_employee(): void
    {
        $division = Division::factory()->create();

        $this->actingAs($this->admin())
            ->put(route('admin.divisions.update', $division), [
                'name' => $division->name,
                'code' => $division->code,
                'division_head_employee_id' => 9999,
            ])
            ->assertSessionHasErrors('division_head_employee_id');
    }

    /**
     * The point of the whole screen: with both heads assigned, an employee in
     * that section can finally have an approval chain resolved. Before this,
     * IpcrRoutingService throws and submission is impossible.
     */
    public function test_assigning_both_heads_unblocks_ipcr_routing(): void
    {
        $division = Division::factory()->create();
        $section = Section::factory()->create(['division_id' => $division->id]);

        $sectionHead = Employee::factory()->create(['section_id' => $section->id]);
        $divisionHead = Employee::factory()->create(['division_id' => $division->id]);
        $rankAndFile = Employee::factory()->create([
            'section_id' => $section->id, 'division_id' => $division->id,
        ]);

        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.sections.update', $section), [
            'division_id' => $division->id,
            'name'        => $section->name,
            'code'        => $section->code,
            'section_head_employee_id' => $sectionHead->id,
        ]);

        $this->actingAs($admin)->put(route('admin.divisions.update', $division), [
            'name' => $division->name,
            'code' => $division->code,
            'division_head_employee_id' => $divisionHead->id,
        ]);

        $chain = app(IpcrRoutingService::class)->resolve($rankAndFile->fresh());

        $this->assertSame($sectionHead->id, $chain->assessor->id);
        $this->assertSame($divisionHead->id, $chain->finalApprover->id);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=HeadAssignmentTest`
Expected: FAIL — the head columns stay null, because the update forms do not send them yet and `$division->fresh()->division_head_employee_id` is null.

If the first two tests already pass (the form requests from Tasks 5 and 6 accept the fields), that is fine — the fourth test is the one that must fail, with `IpcrRoutingException`.

- [ ] **Step 3: Pass employees to the view**

In `OrganizationController@index`, add:

```php
        $employees = Employee::query()
            ->active()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
```

and add `$employees` to the `compact(...)` call. Add `use App\Models\Employee;` to the imports.

- [ ] **Step 4: Add edit modals with head selects**

In `resources/views/admin/organization/index.blade.php`, inside the `@forelse` loop, add an Edit trigger into the division row's actions cell by passing it through the `row-actions` slot:

```blade
                        <x-admin.row-actions :record="$division" :report="$reports[$division->id]"
                            :active-route="route('admin.divisions.active', $division)"
                            :destroy-route="route('admin.divisions.destroy', $division)">
                            <button type="button" x-data
                                x-on:click="$dispatch('open-modal', 'edit-division-{{ $division->id }}')"
                                class="text-sm font-medium text-gray-900 hover:underline">Edit</button>
                        </x-admin.row-actions>
```

Then, still inside the loop, after the section rows, add the per-division edit modal:

```blade
                <x-modal name="edit-division-{{ $division->id }}" focusable max-width="lg">
                    <form method="POST" action="{{ route('admin.divisions.update', $division) }}" class="space-y-4 p-6">
                        @csrf
                        @method('PUT')
                        <h2 class="text-lg font-semibold text-gray-900">Edit division</h2>

                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700">Name</span>
                            <input type="text" name="name" value="{{ $division->name }}" required
                                class="w-full rounded-md border-gray-300 text-sm">
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700">Code</span>
                            <input type="text" name="code" value="{{ $division->code }}" maxlength="20"
                                class="w-full rounded-md border-gray-300 text-sm">
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700">Division Head</span>
                            <select name="division_head_employee_id" class="w-full rounded-md border-gray-300 text-sm">
                                <option value="">No head assigned</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}"
                                        @selected($division->division_head_employee_id === $employee->id)>
                                        {{ $employee->full_name }}</option>
                                @endforeach
                            </select>
                            <span class="mt-1 block text-xs text-gray-500">
                                Required before anyone in this division can submit an IPCR.
                            </span>
                        </label>

                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button"
                                x-on:click="$dispatch('close-modal', 'edit-division-{{ $division->id }}')"
                                class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Cancel</button>
                            <button type="submit"
                                class="rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white hover:bg-nav-800">Save</button>
                        </div>
                    </form>
                </x-modal>
```

Then, inside the `@foreach ($division->sections as $section)` loop, add the per-section edit modal:

```blade
                    <x-modal name="edit-section-{{ $section->id }}" focusable max-width="lg">
                        <form method="POST" action="{{ route('admin.sections.update', $section) }}"
                            class="space-y-4 p-6">
                            @csrf
                            @method('PUT')
                            <h2 class="text-lg font-semibold text-gray-900">Edit section</h2>

                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Division</span>
                                <select name="division_id" required class="w-full rounded-md border-gray-300 text-sm">
                                    @foreach ($divisions as $option)
                                        <option value="{{ $option->id }}"
                                            @selected($section->division_id === $option->id)>{{ $option->name }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Name</span>
                                <input type="text" name="name" value="{{ $section->name }}" required
                                    class="w-full rounded-md border-gray-300 text-sm">
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Code</span>
                                <input type="text" name="code" value="{{ $section->code }}" maxlength="20"
                                    class="w-full rounded-md border-gray-300 text-sm">
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Section Head</span>
                                <select name="section_head_employee_id"
                                    class="w-full rounded-md border-gray-300 text-sm">
                                    <option value="">No head assigned</option>
                                    @foreach ($employees as $employee)
                                        <option value="{{ $employee->id }}"
                                            @selected($section->section_head_employee_id === $employee->id)>
                                            {{ $employee->full_name }}</option>
                                    @endforeach
                                </select>
                                <span class="mt-1 block text-xs text-gray-500">
                                    Required before anyone in this section can submit an IPCR.
                                </span>
                            </label>

                            <div class="flex justify-end gap-3 pt-2">
                                <button type="button"
                                    x-on:click="$dispatch('close-modal', 'edit-section-{{ $section->id }}')"
                                    class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Cancel</button>
                                <button type="submit"
                                    class="rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white hover:bg-nav-800">Save</button>
                            </div>
                        </form>
                    </x-modal>
```

And give the section row an Edit trigger through the `row-actions` slot:

```blade
                            <x-admin.row-actions :record="$section" :report="$sectionReports[$section->id]"
                                :active-route="route('admin.sections.active', $section)"
                                :destroy-route="route('admin.sections.destroy', $section)">
                                <button type="button" x-data
                                    x-on:click="$dispatch('open-modal', 'edit-section-{{ $section->id }}')"
                                    class="text-sm font-medium text-gray-900 hover:underline">Edit</button>
                            </x-admin.row-actions>
```

- [ ] **Step 5: Show the assigned head in the table**

Add a "Head" column to the table header and to both row types:

```blade
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Head</th>
```

```blade
                    <td class="px-6 py-4 text-sm text-gray-600">
                        {{ $division->head?->full_name ?? '—' }}
                    </td>
```

Update every `colspan="4"` to `colspan="5"`.

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=HeadAssignmentTest`
Expected: PASS — 4 tests.

Run: `php artisan test`
Expected: PASS — the whole suite.

- [ ] **Step 7: Rebuild assets and commit** (ask the user first)

```bash
npm run build
git add app resources tests
git commit -m "feat(admin): assign division and section heads, unblocking IPCR routing"
```

---

### Task 8: Job Titles page and Positions

**Files:**
- Create: `app/Http/Controllers/Admin/PositionController.php`
- Create: `app/Http/Requests/Admin/StorePositionRequest.php`
- Create: `app/Http/Requests/Admin/UpdatePositionRequest.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Admin/JobTitleController.php`
- Rewrite: `resources/views/admin/job-titles/index.blade.php`
- Test: `tests/Feature/Admin/PositionManagementTest.php`

**Interfaces:**
- Consumes: `OrgDeletionGuard`, the `x-admin.*` components, the admin route group.
- Produces: named routes `admin.positions.store`, `admin.positions.update`, `admin.positions.active`, `admin.positions.destroy`. `JobTitleController@index` additionally passes `$positionReports` and `$designationReports`, both keyed by id.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/PositionManagementTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PositionManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_an_admin_can_create_a_position(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.positions.store'), [
                'title' => 'Statistician II', 'item_number' => 'STAT-002', 'salary_grade' => 15,
            ])
            ->assertRedirect(route('admin.job-titles.index'));

        $this->assertDatabaseHas('positions', [
            'title' => 'Statistician II', 'item_number' => 'STAT-002', 'salary_grade' => 15, 'is_active' => true,
        ]);
    }

    public function test_a_position_needs_a_title(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.positions.store'), ['title' => ''])
            ->assertSessionHasErrors('title');
    }

    public function test_an_item_number_must_be_unique(): void
    {
        Position::factory()->create(['item_number' => 'STAT-002']);

        $this->actingAs($this->admin())
            ->post(route('admin.positions.store'), ['title' => 'Another', 'item_number' => 'STAT-002'])
            ->assertSessionHasErrors('item_number');
    }

    public function test_a_salary_grade_must_be_within_range(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.positions.store'), ['title' => 'Odd', 'salary_grade' => 99])
            ->assertSessionHasErrors('salary_grade');
    }

    public function test_an_admin_can_update_a_position(): void
    {
        $position = Position::factory()->create(['title' => 'Old']);

        $this->actingAs($this->admin())
            ->put(route('admin.positions.update', $position), [
                'title' => 'New', 'item_number' => $position->item_number, 'salary_grade' => 11,
            ]);

        $this->assertSame('New', $position->fresh()->title);
    }

    public function test_an_admin_can_deactivate_and_reactivate_a_position(): void
    {
        $position = Position::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin())->patch(route('admin.positions.active', $position), ['active' => false]);
        $this->assertFalse($position->fresh()->is_active);

        $this->actingAs($this->admin())->patch(route('admin.positions.active', $position), ['active' => true]);
        $this->assertTrue($position->fresh()->is_active);
    }

    public function test_an_unreferenced_position_can_be_deleted(): void
    {
        $position = Position::factory()->create();

        $this->actingAs($this->admin())->delete(route('admin.positions.destroy', $position));

        $this->assertDatabaseMissing('positions', ['id' => $position->id]);
    }

    public function test_a_position_held_by_an_employee_survives_a_delete_attempt(): void
    {
        $position = Position::factory()->create();
        Employee::factory()->create(['position_id' => $position->id]);

        $this->actingAs($this->admin())->delete(route('admin.positions.destroy', $position));

        $this->assertDatabaseHas('positions', ['id' => $position->id]);
        $this->assertStringContainsString('Cannot delete', (string) session('error'));
    }

    public function test_the_page_lists_positions(): void
    {
        Position::factory()->create(['title' => 'Statistician II']);

        $this->actingAs($this->admin())
            ->get(route('admin.job-titles.index'))
            ->assertOk()
            ->assertSee('Statistician II');
    }

    public function test_a_non_admin_cannot_create_a_position(): void
    {
        $this->seed(RoleSeeder::class);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.positions.store'), ['title' => 'Sneaky'])
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PositionManagementTest`
Expected: FAIL — `Route [admin.positions.store] not defined.`

- [ ] **Step 3: Create the form requests**

Create `app/Http/Requests/Admin/StorePositionRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'        => ['required', 'string', 'max:255'],
            'item_number'  => ['nullable', 'string', 'max:50', 'unique:positions,item_number'],
            // The Philippine salary grade scale runs 1 to 33.
            'salary_grade' => ['nullable', 'integer', 'min:1', 'max:33'],
            'description'  => ['nullable', 'string'],
        ];
    }
}
```

Create `app/Http/Requests/Admin/UpdatePositionRequest.php` — identical, but with the unique rule ignoring the record:

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $positionId = $this->route('position')->id;

        return [
            'title'        => ['required', 'string', 'max:255'],
            'item_number'  => ['nullable', 'string', 'max:50', Rule::unique('positions', 'item_number')->ignore($positionId)],
            'salary_grade' => ['nullable', 'integer', 'min:1', 'max:33'],
            'description'  => ['nullable', 'string'],
        ];
    }
}
```

- [ ] **Step 4: Create the PositionController**

Create `app/Http/Controllers/Admin/PositionController.php`, same shape as `DivisionController`, redirecting to `route('admin.job-titles.index')` (no tab parameter — positions is the default tab):

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePositionRequest;
use App\Http\Requests\Admin\UpdatePositionRequest;
use App\Models\Position;
use App\Services\OrgDeletionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PositionController extends Controller
{
    public function __construct(private readonly OrgDeletionGuard $guard) {}

    public function store(StorePositionRequest $request): RedirectResponse
    {
        $position = Position::create($request->validated() + ['is_active' => true]);

        return redirect()->route('admin.job-titles.index')
            ->with('status', "Created position \"{$position->title}\".");
    }

    public function update(UpdatePositionRequest $request, Position $position): RedirectResponse
    {
        $position->update($request->validated());

        return redirect()->route('admin.job-titles.index')
            ->with('status', "Updated position \"{$position->title}\".");
    }

    public function setActive(Request $request, Position $position): RedirectResponse
    {
        $validated = $request->validate(['active' => ['required', 'boolean']]);

        $position->update(['is_active' => $validated['active']]);

        return redirect()->route('admin.job-titles.index')->with(
            'status',
            ($validated['active'] ? 'Activated' : 'Deactivated') . " position \"{$position->title}\"."
        );
    }

    public function destroy(Position $position): RedirectResponse
    {
        $report = $this->guard->for($position);

        if (! $report->deletable) {
            return redirect()->route('admin.job-titles.index')->with('error', $report->message());
        }

        $title = $position->title;
        $position->delete();

        return redirect()->route('admin.job-titles.index')
            ->with('status', "Deleted position \"{$title}\".");
    }
}
```

- [ ] **Step 5: Add the routes**

Add `use App\Http\Controllers\Admin\PositionController;` and, inside the admin group:

```php
        Route::post('/positions', [PositionController::class, 'store'])->name('positions.store');
        Route::put('/positions/{position}', [PositionController::class, 'update'])->name('positions.update');
        Route::patch('/positions/{position}/active', [PositionController::class, 'setActive'])->name('positions.active');
        Route::delete('/positions/{position}', [PositionController::class, 'destroy'])->name('positions.destroy');
```

- [ ] **Step 6: Add reports to JobTitleController**

Inject the guard and build both report maps:

```php
    public function __construct(private readonly OrgDeletionGuard $guard) {}

    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'designations' ? 'designations' : 'positions';

        $positions = Position::query()->orderBy('title')->get();
        $designations = Designation::query()->orderBy('title')->get();

        $positionReports = $positions->mapWithKeys(fn ($p) => [$p->id => $this->guard->for($p)]);
        $designationReports = $designations->mapWithKeys(fn ($d) => [$d->id => $this->guard->for($d)]);

        return view('admin.job-titles.index', compact(
            'tab', 'positions', 'designations', 'positionReports', 'designationReports'
        ));
    }
```

Add `use App\Services\OrgDeletionGuard;` to the imports.

- [ ] **Step 7: Rewrite the job titles page with tabs and the positions table**

Replace `resources/views/admin/job-titles/index.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Job Titles') }}</h2>
    </x-slot>

    <x-page-container class="space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-emerald-50 p-4 text-sm text-emerald-800 ring-1 ring-emerald-500/20">
                {{ session('status') }}</div>
        @endif

        @if (session('error'))
            <div class="rounded-md bg-red-50 p-4 text-sm text-red-800 ring-1 ring-red-500/20">{{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-md bg-red-50 p-4 text-sm text-red-800 ring-1 ring-red-500/20">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        {{-- Tab state lives in the query string so a redirect after saving
             returns to the tab the administrator was working in. --}}
        <div class="flex items-center justify-between gap-4 border-b border-gray-200">
            <nav class="-mb-px flex gap-6" aria-label="Job title type">
                <a href="{{ route('admin.job-titles.index') }}"
                    class="border-b-2 px-1 pb-3 text-sm font-medium {{ $tab === 'positions' ? 'border-nav-900 text-nav-900' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    Positions ({{ $positions->count() }})
                </a>
                <a href="{{ route('admin.job-titles.index', ['tab' => 'designations']) }}"
                    class="border-b-2 px-1 pb-3 text-sm font-medium {{ $tab === 'designations' ? 'border-nav-900 text-nav-900' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    Designations ({{ $designations->count() }})
                </a>
            </nav>

            <button type="button" x-data
                x-on:click="$dispatch('open-modal', '{{ $tab === 'positions' ? 'create-position' : 'create-designation' }}')"
                class="mb-2 inline-flex items-center rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-nav-800">
                + New {{ $tab === 'positions' ? 'Position' : 'Designation' }}
            </button>
        </div>

        @if ($tab === 'positions')
            <x-admin.table>
                <x-slot:head>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Title</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Item No.
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">SG</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status
                    </th>
                    <th class="px-6 py-3"></th>
                </x-slot:head>

                @forelse ($positions as $position)
                    <tr>
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $position->title }}</td>
                        <td class="px-6 py-4 font-data text-sm text-gray-600">{{ $position->item_number ?? '—' }}</td>
                        <td class="px-6 py-4 font-data text-sm text-gray-600">{{ $position->salary_grade ?? '—' }}</td>
                        <td class="px-6 py-4"><x-admin.active-badge :active="$position->is_active" /></td>
                        <td class="px-6 py-4">
                            <x-admin.row-actions :record="$position" :report="$positionReports[$position->id]"
                                :active-route="route('admin.positions.active', $position)"
                                :destroy-route="route('admin.positions.destroy', $position)" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">No positions yet.</td>
                    </tr>
                @endforelse
            </x-admin.table>

            <x-modal name="create-position" focusable max-width="lg">
                <form method="POST" action="{{ route('admin.positions.store') }}" class="space-y-4 p-6">
                    @csrf
                    <h2 class="text-lg font-semibold text-gray-900">New position</h2>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">Title</span>
                        <input type="text" name="title" required class="w-full rounded-md border-gray-300 text-sm">
                    </label>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700">Item number</span>
                            <input type="text" name="item_number" maxlength="50"
                                class="w-full rounded-md border-gray-300 text-sm">
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700">Salary grade</span>
                            <input type="number" name="salary_grade" min="1" max="33"
                                class="w-full rounded-md border-gray-300 text-sm">
                        </label>
                    </div>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">Description</span>
                        <textarea name="description" rows="3" class="w-full rounded-md border-gray-300 text-sm"></textarea>
                    </label>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" x-on:click="$dispatch('close-modal', 'create-position')"
                            class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Cancel</button>
                        <button type="submit"
                            class="rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white hover:bg-nav-800">Create
                            position</button>
                    </div>
                </form>
            </x-modal>
        @else
            <p class="text-sm text-gray-600">Designations arrive in Task 9.</p>
        @endif
    </x-page-container>
</x-app-layout>
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `php artisan test --filter=PositionManagementTest`
Expected: PASS — 10 tests.

Run: `php artisan test`
Expected: PASS — the whole suite.

- [ ] **Step 9: Rebuild assets and commit** (ask the user first)

```bash
npm run build
git add app resources routes tests
git commit -m "feat(admin): manage positions on a tabbed Job Titles page"
```

---

### Task 9: Designations tab

**Files:**
- Create: `app/Http/Controllers/Admin/DesignationController.php`
- Create: `app/Http/Requests/Admin/StoreDesignationRequest.php`
- Create: `app/Http/Requests/Admin/UpdateDesignationRequest.php`
- Modify: `routes/web.php`
- Modify: `resources/views/admin/job-titles/index.blade.php`
- Test: `tests/Feature/Admin/DesignationManagementTest.php`

**Interfaces:**
- Consumes: everything from Task 8.
- Produces: named routes `admin.designations.store`, `admin.designations.update`, `admin.designations.active`, `admin.designations.destroy`. All redirect to `route('admin.job-titles.index', ['tab' => 'designations'])`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/DesignationManagementTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\Designation;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignationManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_an_admin_can_create_a_designation(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.designations.store'), ['title' => 'OIC - Budget Officer'])
            ->assertRedirect(route('admin.job-titles.index', ['tab' => 'designations']));

        $this->assertDatabaseHas('designations', ['title' => 'OIC - Budget Officer', 'is_active' => true]);
    }

    public function test_a_designation_needs_a_title(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.designations.store'), ['title' => ''])
            ->assertSessionHasErrors('title');

        $this->assertSame(0, Designation::count());
    }

    public function test_an_admin_can_update_a_designation(): void
    {
        $designation = Designation::factory()->create(['title' => 'Old']);

        $this->actingAs($this->admin())
            ->put(route('admin.designations.update', $designation), ['title' => 'New']);

        $this->assertSame('New', $designation->fresh()->title);
    }

    public function test_an_admin_can_deactivate_and_reactivate_a_designation(): void
    {
        $designation = Designation::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin())->patch(route('admin.designations.active', $designation), ['active' => false]);
        $this->assertFalse($designation->fresh()->is_active);

        $this->actingAs($this->admin())->patch(route('admin.designations.active', $designation), ['active' => true]);
        $this->assertTrue($designation->fresh()->is_active);
    }

    public function test_an_unreferenced_designation_can_be_deleted(): void
    {
        $designation = Designation::factory()->create();

        $this->actingAs($this->admin())->delete(route('admin.designations.destroy', $designation));

        $this->assertDatabaseMissing('designations', ['id' => $designation->id]);
    }

    public function test_the_designations_tab_lists_them(): void
    {
        Designation::factory()->create(['title' => 'OIC - HRMO']);

        $this->actingAs($this->admin())
            ->get(route('admin.job-titles.index', ['tab' => 'designations']))
            ->assertOk()
            ->assertSee('OIC - HRMO');
    }

    public function test_the_positions_tab_does_not_show_designations(): void
    {
        Designation::factory()->create(['title' => 'OIC - HRMO']);
        Position::factory()->create(['title' => 'Statistician II']);

        $this->actingAs($this->admin())
            ->get(route('admin.job-titles.index'))
            ->assertOk()
            ->assertSee('Statistician II')
            ->assertDontSee('OIC - HRMO');
    }

    public function test_a_non_admin_cannot_create_a_designation(): void
    {
        $this->seed(RoleSeeder::class);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.designations.store'), ['title' => 'Sneaky'])
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=DesignationManagementTest`
Expected: FAIL — `Route [admin.designations.store] not defined.`

- [ ] **Step 3: Create the form requests**

Create `app/Http/Requests/Admin/StoreDesignationRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreDesignationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Designations have no code column, unlike divisions and sections.
        return [
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }
}
```

Create `app/Http/Requests/Admin/UpdateDesignationRequest.php` with the same rules — there is no unique column to ignore:

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDesignationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }
}
```

- [ ] **Step 4: Create the DesignationController**

Create `app/Http/Controllers/Admin/DesignationController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDesignationRequest;
use App\Http\Requests\Admin\UpdateDesignationRequest;
use App\Models\Designation;
use App\Services\OrgDeletionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DesignationController extends Controller
{
    public function __construct(private readonly OrgDeletionGuard $guard) {}

    /** Every redirect carries the tab so the administrator lands back where they were. */
    private function backToTab(string $message, string $key = 'status'): RedirectResponse
    {
        return redirect()
            ->route('admin.job-titles.index', ['tab' => 'designations'])
            ->with($key, $message);
    }

    public function store(StoreDesignationRequest $request): RedirectResponse
    {
        $designation = Designation::create($request->validated() + ['is_active' => true]);

        return $this->backToTab("Created designation \"{$designation->title}\".");
    }

    public function update(UpdateDesignationRequest $request, Designation $designation): RedirectResponse
    {
        $designation->update($request->validated());

        return $this->backToTab("Updated designation \"{$designation->title}\".");
    }

    public function setActive(Request $request, Designation $designation): RedirectResponse
    {
        $validated = $request->validate(['active' => ['required', 'boolean']]);

        $designation->update(['is_active' => $validated['active']]);

        return $this->backToTab(
            ($validated['active'] ? 'Activated' : 'Deactivated') . " designation \"{$designation->title}\"."
        );
    }

    public function destroy(Designation $designation): RedirectResponse
    {
        $report = $this->guard->for($designation);

        if (! $report->deletable) {
            return $this->backToTab($report->message(), 'error');
        }

        $title = $designation->title;
        $designation->delete();

        return $this->backToTab("Deleted designation \"{$title}\".");
    }
}
```

- [ ] **Step 5: Add the routes**

Add `use App\Http\Controllers\Admin\DesignationController;` and, inside the admin group:

```php
        Route::post('/designations', [DesignationController::class, 'store'])->name('designations.store');
        Route::put('/designations/{designation}', [DesignationController::class, 'update'])->name('designations.update');
        Route::patch('/designations/{designation}/active', [DesignationController::class, 'setActive'])->name('designations.active');
        Route::delete('/designations/{designation}', [DesignationController::class, 'destroy'])->name('designations.destroy');
```

- [ ] **Step 6: Replace the designations placeholder in the view**

In `resources/views/admin/job-titles/index.blade.php`, replace the `@else` branch (`<p ...>Designations arrive in Task 9.</p>`) with:

```blade
        @else
            <x-admin.table>
                <x-slot:head>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Title</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status
                    </th>
                    <th class="px-6 py-3"></th>
                </x-slot:head>

                @forelse ($designations as $designation)
                    <tr>
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $designation->title }}</td>
                        <td class="px-6 py-4"><x-admin.active-badge :active="$designation->is_active" /></td>
                        <td class="px-6 py-4">
                            <x-admin.row-actions :record="$designation" :report="$designationReports[$designation->id]"
                                :active-route="route('admin.designations.active', $designation)"
                                :destroy-route="route('admin.designations.destroy', $designation)" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-6 py-8 text-center text-sm text-gray-500">No designations yet.</td>
                    </tr>
                @endforelse
            </x-admin.table>

            <x-modal name="create-designation" focusable max-width="lg">
                <form method="POST" action="{{ route('admin.designations.store') }}" class="space-y-4 p-6">
                    @csrf
                    <h2 class="text-lg font-semibold text-gray-900">New designation</h2>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">Title</span>
                        <input type="text" name="title" required placeholder="OIC - Budget Officer"
                            class="w-full rounded-md border-gray-300 text-sm">
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">Description</span>
                        <textarea name="description" rows="3" class="w-full rounded-md border-gray-300 text-sm"></textarea>
                    </label>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" x-on:click="$dispatch('close-modal', 'create-designation')"
                            class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Cancel</button>
                        <button type="submit"
                            class="rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white hover:bg-nav-800">Create
                            designation</button>
                    </div>
                </form>
            </x-modal>
        @endif
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test --filter=DesignationManagementTest`
Expected: PASS — 8 tests.

- [ ] **Step 8: Run the whole suite and rebuild**

Run: `php artisan test`
Expected: PASS — every test.

Run: `npm run build`
Expected: build succeeds.

- [ ] **Step 9: Verify by hand in the browser**

```bash
php artisan migrate:fresh --seed
```

Then sign in at `http://ipcr-system-laravel.test/login` as `admin@example.com` / `password` and confirm:

1. The Administration group appears in the sidebar.
2. `/admin/organization` lists divisions with their sections nested.
3. Creating a division, then a section inside it, both work.
4. Assigning a Division Head and a Section Head both save.
5. `/admin/job-titles` switches between the Positions and Designations tabs.
6. Delete is greyed out on a record that has references, with a tooltip naming them.

- [ ] **Step 10: Commit** (ask the user first)

```bash
git add app resources routes tests
git commit -m "feat(admin): manage designations on the Job Titles page"
```

---

## Completion notes

State these plainly when reporting the work as done:

- **Running `php artisan db:seed` is required** before the admin area can be reached. Without it there is no `admin` role and no `admin@example.com`, and every admin route answers 403 — which looks exactly like a bug.
- **Credentials:** `admin@example.com` / `password`. The account has no `Employee` record, so it cannot create an IPCR of its own; that is deliberate.
- **The head selects are empty on a fresh database** with no employees, and IPCR submission stays blocked until at least one employee exists and is assigned as a head. `DemoSeeder` creates employees, so a seeded database is fine. This is the strongest argument for making Employee management Phase 2.

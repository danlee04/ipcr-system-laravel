# Admin — Organizational Data Management (Phase 1)

Date: 2026-08-25
Status: Approved for planning

## Problem

The IPCR system has no admin interface. Divisions, sections, positions and
designations can only be created by editing seeders, and the head assignments
that drive `IpcrRoutingService` can only be set in the database by hand.

That last point is not cosmetic. `IpcrRoutingService` resolves an employee's
approval chain from `divisions.division_head_employee_id` and
`sections.section_head_employee_id`. If those are unset, **no employee can
submit an IPCR at all** — submission fails with an `IpcrRoutingException`.
Today there is no supported way to set them.

There is also no authorization layer. `spatie/laravel-permission` v8.3 is
installed and its tables are migrated, but the `User` model does not use
`HasRoles`, and the `roles`, `permissions` and `model_has_roles` tables are all
empty. Nothing in the app currently distinguishes an administrator from any
other signed-in user.

## Scope

**Phase 1 delivers two screens plus the authorization layer they need.**

| Screen | Route | Covers |
| --- | --- | --- |
| Organization | `/admin/organization` | Divisions, their Sections, and both head assignments |
| Job Titles | `/admin/job-titles` | Positions and Designations, as two tabs |

Explicitly **not** in Phase 1, each to get its own spec later:

- Employees (user linking, position/section assignment, designation pivot with
  dates and Office Order reference)
- Job Functions (the catalog behind `FunctionCatalogService`)
- IPCR Periods
- Assigning roles through the UI

## Access control

### Roles

Attach `Spatie\Permission\Traits\HasRoles` to `App\Models\User`.

A new `RoleSeeder` creates three roles on the `web` guard:

| Role | Phase 1 meaning |
| --- | --- |
| `admin` | Full access to the admin area |
| `hr` | Created but unused; reserved for a later split of admin duties |
| `employee` | Created but unused; the default for everyone else |

Only `admin` grants access in Phase 1. `hr` and `employee` exist so that a later
split of responsibilities needs no migration — just policy changes.

### Bootstrapping the first admin

There is no UI for granting roles in Phase 1, which leaves a bootstrap problem:
somebody has to be an admin before anyone can use the admin area.

`DemoSeeder` will create a dedicated account and give it the `admin` role:

| | |
| --- | --- |
| Email | `admin@example.com` |
| Password | `password` (same as the other seeded accounts) |
| Employee record | **None** |

The account deliberately has no `Employee` record. A system administrator and a
rated employee are different things, and keeping them apart means nothing in the
admin area can quietly come to depend on `auth()->user()->employee` being
present. It also exercises the null-employee path, which is easy to break
without noticing — `IpcrController` already aborts with 403 when an employee
record is missing, and `layouts/sidebar.blade.php` falls back to showing the
email instead of an employee number.

The three existing seeded accounts keep their current roles in the IPCR flow and
gain no admin access:

| Email | Role in the flow |
| --- | --- |
| `test@example.com` | rank and file, submits an IPCR |
| `sectionhead@example.com` | assessor |
| `divisionhead@example.com` | final approver |

Running `php artisan db:seed` is therefore a prerequisite for reaching the admin
area, and the implementation must state this in its completion notes.

### Route protection

Protection is applied at the route group, never per controller method:

```php
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'role:admin'])
    ->group(function () { /* ... */ });
```

Group-level middleware means a newly added admin controller is protected by
default. A per-controller check would be one forgotten line away from an open
endpoint.

`verified` is deliberately **not** in that list. `MustVerifyEmail` is commented
out on `App\Models\User`, so email verification is not enforced anywhere in this
app; the `verified` middleware already on `/dashboard` passes unconditionally.
Adding it here would imply a guarantee that does not exist. If verification is
turned on later, it should be added to every authenticated route at once, not
just this group.

`Spatie\Permission\Middleware\RoleMiddleware` must be registered as the `role`
alias in `bootstrap/app.php`. Its `withMiddleware` closure is currently empty,
so this registration does not exist yet — without it, `role:admin` throws
rather than denying access.

A non-admin hitting any admin route receives **403**, not a redirect — the
route should not confirm what exists behind it.

## Screens

Both screens use the existing `<x-app-layout>` and `<x-page-container>`. There
is no separate admin theme: one visual system, and the standing rule that pages
fill the available width applies here too.

### Organization (`/admin/organization`)

One page showing every Division with its Sections nested underneath, because a
Section cannot exist without a Division (`sections.division_id` is required).
Seeing the whole tree at once is the point of the screen.

Per Division: name, code, active state, assigned Division Head, and its
Sections. Per Section: name, code, active state, assigned Section Head.

Create and edit happen in modals (`<x-modal>`), so the tree stays on screen
while editing a single node.

**Head assignment** is a select of active employees, present on both the
Division and the Section forms. This is the part that unblocks IPCR submission,
so it is not optional polish.

### Job Titles (`/admin/job-titles`)

One page with two tabs.

| Tab | Model | Fields |
| --- | --- | --- |
| Positions | `Position` | title, item_number, salary_grade, description, is_active |
| Designations | `Designation` | title, description, is_active |

They share a page because an administrator thinks of both as "job titles you
assign to people". They stay separate models, controllers, and form requests
because they mean different things and validate differently: a Position is the
employee's single plantilla post and the source of CORE functions; a
Designation is an extra assignment (for example "OIC - Budget") that an employee
may hold several of at once, and is the source of STRATEGIC and SUPPORT
functions.

Tab state lives in the query string (`?tab=designations`) so a redirect after
saving returns to the tab the user was on.

## Controllers and routes

Separate resource controllers per model, sharing presentation only:

```
App\Http\Controllers\Admin\
    OrganizationController      index (the tree)
    DivisionController          store, update, destroy, setActive
    SectionController           store, update, destroy, setActive
    JobTitleController          index (the tabbed page)
    PositionController          store, update, destroy, setActive
    DesignationController       store, update, destroy, setActive
```

`setActive` is a single `PATCH admin/{resource}/{id}/active` route taking an
explicit `active` boolean, not a toggle that flips whatever it finds. A toggle
sends the wrong thing when two tabs disagree about the current state; an
explicit value always lands where the user intended.

`OrganizationController` and `JobTitleController` own the two pages. The four
model controllers own the writes. This keeps each model's validation and
behaviour explicit while the pages stay composed.

Form requests per model: `StoreDivisionRequest`, `UpdateDivisionRequest`, and
the same for the other three.

### Shared UI components

| Component | Purpose |
| --- | --- |
| `<x-admin.table>` | Consistent table shell: header, empty state, spacing |
| `<x-admin.row-actions>` | Edit / Activate / Deactivate / Delete cluster |
| `<x-admin.active-badge>` | Active / Inactive pill |
| `<x-admin.form-card>` | Modal form body: title, fields slot, save/cancel |

Shared where the markup repeats, explicit where behaviour differs.

## Deactivate and delete

Deactivating is the normal way to retire a record; deleting is the exception.
This matches what the schema already says: every one of the four tables carries
`is_active`, and the foreign keys use `restrictOnDelete`.

A deactivated record keeps all history attached to it and simply stops
appearing in new pickers.

### `OrgDeletionGuard`

A single service answering one question per record: **what still references
this?**

```php
final readonly class DeletionReport
{
    public function __construct(
        public bool $deletable,
        /** @var array<string,int> e.g. ['sections' => 3, 'employees' => 12] */
        public array $blockers,
    ) {}
}
```

| Model | Checked references |
| --- | --- |
| Division | sections, employees |
| Section | employees |
| Position | job functions, employees |
| Designation | job functions, employee designations |

The **Delete** action renders disabled when `blockers` is non-empty, with the
counts spelled out: *"Cannot delete — 3 sections and 12 employees reference
this. Deactivate it instead."*

The guard is re-run server-side in `destroy()` before deleting. The view state
is a convenience; the server check is the actual rule. Without it, a stale tab
could delete a record that gained a reference in the meantime.

## Testing

Test-driven, one behaviour per test.

**Access control** — for every admin route: a guest is redirected to login; a
signed-in non-admin gets 403; an admin gets 200. The non-admin case is
parameterised over the full route list so a new admin route added without
protection fails the suite.

**Navigation** — the Administration group renders for an admin and does not
render for a non-admin.

**Admin without an employee record** — the seeded admin has no `Employee`, so
every admin page must render for a user whose `employee` relation is null. This
is asserted directly rather than left to chance.

**Per model** (Division, Section, Position, Designation):

- create with valid input
- validation rejects a missing name/title
- validation rejects a duplicate code where the column is unique
- update
- deactivate, then activate
- delete succeeds when nothing references the record
- delete is refused when something does, and the record survives

**Section-specific** — creating a Section requires an existing `division_id`.

**Head assignment** — assigning a Division Head and a Section Head, then
asserting `IpcrRoutingService` resolves a chain for an employee in that section.
This ties the screen to the reason it exists.

**`OrgDeletionGuard`** — unit tests for each model's blocker counts, including
the zero case.

## Risks

**The bootstrap step is easy to miss.** Anyone setting up the project fresh has
to run `php artisan db:seed` before the admin area is reachable, and the failure
mode is a bare 403 that looks like a bug. The implementation notes must call
this out.

**Head assignment creates a chicken-and-egg case.** A Division Head must be an
existing employee, but Phase 1 has no employee management. On a fresh database
with no employees, the head selects are empty and IPCR submission stays blocked.
This is acceptable for Phase 1 because `DemoSeeder` creates employees, but it is
the strongest argument for making Employees the next phase.

## Deliverables

- `HasRoles` on `User`; `role` middleware alias registered
- `RoleSeeder`; `DemoSeeder` grants `admin` to the test account
- Admin route group with six controllers and eight form requests
- Two pages: Organization, Job Titles
- Four shared `x-admin.*` components
- `OrgDeletionGuard` and `DeletionReport`
- Administration nav group, visible to admins only
- Full test suite as described above

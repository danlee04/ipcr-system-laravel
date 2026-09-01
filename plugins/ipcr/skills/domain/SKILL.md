---
name: domain
description: The IPCR form itself - how a sheet is built, scored, routed and approved. Read before touching anything that rates, weights, routes or approves an IPCR, or that adds functions to the catalog. Triggers on "IPCR", "rating", "rubric", "measure", "Q/E/T", "approval chain", "assessor", "final approver", "job function", "catalog", "period", "weight", "adjectival".
---

# The IPCR, as this system understands it

An IPCR is one employee's sheet for one rating period. It lists the work they
committed to, what they actually accomplished, and a mark out of five for each
line. There is at most one per employee per period — the database enforces it
with a unique key on `(employee_id, ipcr_period_id)`.

## The line

An `IpcrItem` is one row of the form:

| Column | What it is |
|---|---|
| `output` | The MFO — a title. "Awarded Contract". Four words at the most. |
| `success_indicator` | The target, a whole sentence with figures in it |
| `actual_accomplishment` | What happened, in the same shape as the indicator |
| `category` | Core, Support or Strategic — what kind of work it is |
| `weight` | This line's share of its category, worked out automatically |

## Three measures, marked out of five

`RatingMeasure` is Quality, Efficiency and Timeliness — **Q**, **E**, **T**.
A line is not marked on all three; most outputs have no Timeliness at all. A
measure left blank is n/a, which is a real answer and not a zero, and the
line's average is taken over the measures that apply.

## The rubric turns figures into both the sentence and the marks

A catalog function may carry a rubric: per measure, how it is answered
(`MeasureAnswer` — a picked descriptor, a typed number, or a count out of a
total) and five bands saying which figure earns which mark.

The employee reports figures. `AccomplishmentWriter` then does two things:

1. Fills the function's `accomplishment_template` — `{e}`, `{e_ratio}`,
   `{t_when}` and so on — to produce the accomplishment sentence.
2. Reads each figure against the bands to produce the mark.

So the same performance reads and scores the same way whoever reports it.

**Placeholders only exist if something can fill them.** `{e_ratio}` needs
Efficiency answered by a count; `{t_when}` needs Timeliness answered by a
number in days. The form refuses a template naming one that nothing fills, and
says which setting to change.

**A measure can be reported without being rated.** Real wordings carry a figure
nobody marks — "100% of reports within 12 days" is one sentence, two figures,
one mark. A measure with no bands is printed and never scored.

**Negatives.** A figure below zero is refused unless the scale is written
across zero — a timeliness ladder counted from the deadline, where -5 means
five days early and earns the top mark. `FunctionMeasure::acceptsNegative()`
decides by looking at whether any band bound is negative.

## From marks to a final rating

`RatingCalculator`. Each line averages its measures; each category averages its
lines by weight; the categories are then combined by a split that depends on
what the employee actually holds:

- With no strategic function: Core 80, Support 20
- With one: Strategic 40, Core 50, Support 10

The final number maps to an `AdjectivalRating` — Outstanding, Very
Satisfactory, Satisfactory, Unsatisfactory, Poor.

**Item weights are automatic and equal.** `ItemWeights` splits a category's
hundred evenly across its lines, counted in hundredths so thirds come out
exact (33.33 / 33.33 / 33.34). Nobody types a weight. Adding or removing a
line re-shares that category.

## The catalog: what an employee may put on their sheet

`FunctionCatalogService::availableFor()` returns four buckets:

- `core`, `support`, `strategic` — everything reaching this employee through
  their plantilla position, a designation they currently hold, or open to
  everyone
- `elsewhere` — work filed under **another post**, offered separately so
  borrowing a line is a deliberate act. People cover vacancies.

Two doors stay shut. A **designation** is an appointment somebody holds, so
its functions are theirs alone. A **retired** function is retired for everyone.
The controller checks every submitted id against this list — a form sends a
list of numbers, and a crafted one can name anything.

A function tied to no position and no designation is a **common function**.
That is the word used everywhere: not "everyone", not "shared".

## The approval chain

`IpcrRoutingService` reads it off the org chart every time rather than storing
it, so a change of head carries. The order of the checks matters — a promoted
division head may still carry a section id.

| Who submits | Assessment | Final approval |
|---|---|---|
| Rank and file | their section head | that section's division head |
| Section head | their division head | Chief of Hospital |
| Division head | Chief of Hospital | Chief of Hospital |
| Chief of Hospital | routed by hand by admin or HR | |

**The owner rates themselves.** Whoever writes the IPCR gives their own marks;
the supervisors assess and approve, they do not re-mark it.

`IpcrStatus` runs Draft → Submitted → Assessed → Approved, with Returned as
the way back. Only Draft is editable by the owner. Admin and HR may delete an
IPCR at any status except Approved.

## Periods

`IpcrPeriod` has a status and an optional `submission_deadline`. A late
submission is **marked, never blocked** — the sheet still goes through, and it
carries a late badge.

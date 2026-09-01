# DTRC IPCR System

An Individual Performance Commitment and Review system for a Philippine
government hospital, following the Civil Service Commission's IPCR form.
Employees write their own sheet, rate themselves against a rubric HR wrote,
and send it up a two-step approval chain.

- Laravel 13 on PHP 8.3, PHPUnit 12, Blade with Alpine 3 and Tailwind v4
- Roles through spatie/laravel-permission: `admin`, `hr`, `employee`
- Local URL: `ipcr-system-laravel.test` (Laragon). There is a sibling project
  at `ipcr-system.test` — not this one.

## Working agreement

**Never run `git commit` or `git push`.** Dan makes every commit himself. When
a piece of work is finished, hand him one commit message covering the whole
feature. Keep it plain: a subject line naming what was edited, and at most two
lines of body. Never `feat:`/`fix:` prefixes, and never a metaphor or a clever
phrasing of the idea — "Fix the status distribution chart size and layout",
not "Box the doughnut and give it back a neighbour".

**Never write to the dev database.** `ipcr_laravel_db` holds the hospital's
real reference data, entered by hand. Do not create employees, IPCRs, periods
or anything else there. Verify in the test suite, which runs on in-memory
SQLite. If something genuinely has to be checked against MySQL, ask first and
say what will be written.

**English only** in comments, commit messages, and every string a user sees.
The conversation may be in Taglish; the codebase is not.

## Before saying a piece of work is done

```
php artisan test        # all of it, not the file you touched
npm run build           # whenever a Blade view, CSS or JS changed
```

Tailwind v4 scans the source for literal class names, so a new utility only
exists after a build. Check the built CSS if a class is doing nothing.

## Where things live

| | |
|---|---|
| `app/Services/` | The decisions. `IpcrRoutingService` (who approves), `RatingCalculator` (the numbers), `AccomplishmentWriter` (figures into a sentence), `FunctionCatalogService` (what an employee may pick) |
| `app/Enums/` | The vocabulary. `IpcrStatus`, `FunctionCategory`, `RatingMeasure`, `AdjectivalRating` |
| `resources/views/components/` | Blade components, namespaced by screen: `admin.*`, `ipcr.*`, `dashboard.*` |
| `docs/superpowers/` | Specs and plans from earlier design sessions |

## Skills in this repository

Read the one that covers what you are about to do. They are in
`.claude/skills/`:

- **ipcr-domain** — the CSC form, the rubric, the approval chain, the catalog.
  Read it before touching anything that scores or routes an IPCR.
- **ipcr-testing** — how work is verified here: red first, mutate to prove the
  test has teeth, then the full suite.
- **blade-alpine-pitfalls** — four bug classes this project has hit more than
  once. Read it before writing a form, a table, or an Alpine filter.
- **ui-conventions** — the palette, the shell, and how a screen is laid out.

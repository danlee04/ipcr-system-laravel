---
name: testing
description: How work is verified in this project - write the failing test first, mutate the code to prove the test has teeth, run the whole suite and build before claiming anything is done. Read before writing a test, fixing a bug, or reporting that a piece of work is finished. Triggers on "test", "PHPUnit", "factory", "verify", "done", "fixed", "regression", "assert", "coverage".
---

# Verifying work here

## Red first, always

Write the test, run it, and **read the failure**. A test that fails for the
wrong reason — a typo, a missing route, a factory blowing up — proves nothing.
The message should say the feature is missing.

```
php artisan test tests/Feature/Ipcr/TheThing.php
```

The suite runs on in-memory SQLite, so every query must be portable. `||` is
OR in MySQL and string concatenation in SQLite; a `CASE` expression and
`orderByRaw` with bindings work in both.

Output is JSON. To read a run without drowning in HTML dumps:

```bash
php artisan test > /tmp/run.json 2>&1; php -r '
$d = json_decode(file_get_contents("/tmp/run.json"), true);
echo $d["tests"]." tests, ".$d["passed"]." passed, ".($d["failed"] ?? 0)." failed".PHP_EOL;
foreach (array_merge($d["failures"] ?? [], $d["error_details"] ?? []) as $f) {
    echo "- ".$f["test"].": ".substr(preg_replace("/\s+/", " ", $f["message"]), 0, 200).PHP_EOL;
}'
```

## Then prove the test has teeth

A test that passes is not yet evidence. Break the thing it guards and check it
notices:

```bash
cp app/Services/Thing.php /tmp/thing.bak
# remove the clause the test is about
php artisan test tests/Feature/ThingTest.php
cp /tmp/thing.bak app/Services/Thing.php
```

**Never `git checkout` a file to undo a mutation.** Working changes in this
repo are usually uncommitted, and checkout throws them away. Copy the file
aside and copy it back.

If the mutation is not caught, the test is decoration — or the code it guards
is unreachable. Both are worth knowing. A clause that no mutation can break is
dead code: delete it rather than leave something that looks like a guard.

## When an existing test contradicts new behaviour

Change what it means; do not delete it. A test that said "a function from
another post is refused" became a test that says "a function under a
designation is refused" — the boundary moved, the guard stayed.

## Don't add markup for the benefit of a test

If a test needs a hook in the HTML, find one that is already earning its place.
A row heading a group of rows is `<th scope="colgroup">` — real markup a screen
reader announces, and something the test can match on. A `data-testid` that
serves nothing else is a smell.

Asserting on Tailwind classes is acceptable where the class *is* the
behaviour — a fixed-size box, a two-column grid — because there is no other way
to express it from PHP. Assert on the specific element, not on the class
appearing anywhere on the page.

## Before saying it is done

```
php artisan test        # the whole suite
npm run build           # if any view, CSS or JS changed
```

Report the real numbers. If something is still failing, say so with the output.
If part of the work was left out, say what and why.

## Test data

Factories live in `database/factories`. Watch for:

- One IPCR per employee per period — two `Ipcr::factory()->count(2)` rows for
  the same employee and period violate a unique key.
- `withHeaders()` persists across requests in one test. A second `get()` after
  a live-list probe will still carry `X-Live-List` and come back as a partial.
- Blade renders attributes across newlines, so `<th[^>]*>` beats a regex that
  assumes one line, and `[^>]*` breaks on an Alpine handler containing `=>`.

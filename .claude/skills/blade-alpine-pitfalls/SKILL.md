---
name: blade-alpine-pitfalls
description: Four bug classes this project has shipped more than once - hidden form controls that still submit, modals inside table bodies, x-model bound to nothing, and canvases sized by their column. Read before writing a form, a filtered select, a table with row actions, or a chart. Triggers on "form", "checkbox", "select", "filter", "modal", "table", "x-model", "x-show", "Alpine", "chart", "canvas".
---

# Four things that have bitten this project

Each of these shipped, reached the screen, and had to be found by dumping HTML.
They are cheap to avoid and expensive to diagnose.

## 1. A hidden control is still submitted

`x-show` sets `display: none`. It does not take the control out of the form.

- A **checked checkbox** that a filter has just hidden is still posted. The
  employee adds work they cannot see and never chose.
- A **selected `<option>`** hidden by `x-show` is still the select's value.
  Narrowing a division left a position from another division selected, and the
  pair could only ever return nothing.

**What to do.** When a filter hides something, clear it.

- Selects: clear what sits below in the change handler —
  `x-on:change="section = ''; position = ''"`.
- Checkboxes: untick them, and dispatch a real `change` event so whatever was
  counting them updates. Do not adjust the counter by hand in two places.

```js
prune() {
    this.$el.querySelectorAll('input[type="checkbox"]:checked').forEach((box) => {
        if (box.closest('[data-post]')?.dataset.post === this.position) return;
        box.checked = false;
        box.dispatchEvent(new Event('change', { bubbles: true }));
    });
}
```

A select that only filters what is on screen should carry **no `name`**, so it
is never posted at all.

## 2. A `<div>` inside `<tbody>` is hoisted out of the table

Invalid HTML. The parser fosters it out, and a modal meant to stay hidden until
asked for leaks onto the page — this is how a whole rubric editor ended up
printed under a list of functions.

**What to do.** Render modals in a second loop after `</table>`, never inside a
row. A `<th scope="colgroup">` inside `<tbody>` *is* valid and is the right way
to head a group of rows.

## 3. `x-model` bound to a property that does not exist

Alpine writes the (undefined) value into the element, the browser falls back to
the first `<option>`, and every form silently picks the wrong one. A category
select showed Strategic for every function, new or existing.

**What to do.** Every `x-model` name must appear in the enclosing `x-data`.
When the select is only there to be submitted, it needs no `x-model` at all.

## 4. A canvas sized by its column

Chart.js writes the size it chose into an inline style on the canvas. Two
consequences:

- A doughnut fills the **smaller** side of its box, so in a wide card it is a
  small ring in a field of nothing.
- A page restored with the Back button keeps that inline size while the layout
  around it is computed again, so the canvas can end up at the wrong width and
  spill over the panel beside it. `DOMContentLoaded` does not fire on a
  bfcache restore.

**What to do.** Give a doughnut a fixed square (`h-40 w-40`); let only the bar
chart take the column width. Put `overflow-hidden` on every chart box. Redraw
on restore, and keep one chart per canvas:

```js
const charts = new WeakMap();

function draw(canvas, settings) {
    charts.get(canvas)?.destroy();
    charts.set(canvas, new Chart(canvas, settings));
}

window.addEventListener('pageshow', (event) => {
    if (event.persisted) drawCharts();
});
```

## Also worth knowing

- **`FormRequest::validated()` returns only keys that have a rule.** A nested
  array with no `field.*` rule is silently dropped.
- **Alpine settles after the event, not during it.** Reading a form inside a
  `change` handler can see the value that was just cleared. Wrap the read:
  `$nextTick(() => this.load())`.
- **Tailwind v4 only emits classes it can see as literal text.** A class built
  by string concatenation in PHP will not exist. Spell each one out, as
  `kpi-card.blade.php` does with its accents.

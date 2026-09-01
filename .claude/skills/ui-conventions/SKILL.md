---
name: ui-conventions
description: The palette, the app shell and how a screen is put together in this project - colour tokens, page width, tables, live-filtered lists and the words used on screen. Read before building or restyling any page. Triggers on "colour", "color", "palette", "Tailwind", "layout", "page", "table", "filter", "sidebar", "badge", "component", "styling".
---

# How a screen is built here

## Colour

Three colours were chosen and everything is derived from them in
`resources/css/app.css`. Never reach past the tokens to a raw hex.

| Token | | |
|---|---|---|
| `brand-600` | `#015ae6` | the blue the system is signed in |
| `mint-500` | `#25c7a3` | the accent, and every "this went well" |
| `mint-200` | `#95f9df` | the same accent on dark ground |
| `ink-*` | | neutrals — the brand hue with the colour drained out |
| `nav-900…100` | | the app shell, the bottom of the brand ladder |
| `accent` / `accent-bright` | | the rail on an active nav item, focus rings |

The stock scales are pointed at these: `gray` and `slate` resolve to `ink`,
`blue`/`indigo`/`sky` to `brand`, `emerald`/`teal`/`green` to `mint`. So a
class saying `text-gray-500` is already on palette and does not need changing.

**Amber and red are deliberately untouched.** They are signals, not decoration:
a late submission and a returned IPCR have to keep reading as warnings, and a
delete button gone mint would be a trap.

Badge colours live on the enums (`IpcrStatus::badgeClasses()` and friends), so
one status looks the same everywhere.

## The page

Every screen is `<x-app-layout>` with a `header` slot and an
`<x-page-container>` body. **Use the full width** — no narrow centred column.

The sidebar groups its links under headings (`<x-sidebar-heading>`), and each
heading answers "why can I see these": My Work, Approvals, Administration.
Collapsed, a rule stands in for the heading the icons have no room for. Rows
are `min-h-11 lg:min-h-10` — 44px is the touch target a phone needs; the
desktop rows are read with a mouse and can be tighter.

## Tables

`<x-admin.table>` is the shared shell. Beyond that:

- **Spend the width where the words are.** An output is a title and a success
  indicator is a sentence; equal columns waste room on one and clamp the other.
  Give the longest column no width at all so it absorbs what is left — that
  also keeps the table full when a conditional last column disappears.
- Clamp long text with `line-clamp-*` and put the whole string in `title`,
  rather than truncating in PHP.
- Centre a column of badges; leave sentences left.
- Group rows under `<th scope="colgroup">`, and say the group once.

## Lists that filter without reloading

Wrap the filter form and the rows in `<x-admin.live-list :action="...">`, mark
the form `data-live-form` (`<x-admin.filter-bar>` does) and the rows
`data-live-results` (`<x-admin.live-results>` does). The controller uses the
`RendersLiveLists` trait and returns the rows partial alone when the request
carries `X-Live-List`.

It works with JavaScript off: the form is a plain GET form and the same action
serves the whole page. Paging links inside the results are intercepted too, so
turning a page does not reload. The results dim only if the request takes
longer than 200ms — dimming for forty milliseconds reads as a page reload,
which is the thing being avoided.

## Words

- Say what a thing is, in the words the hospital uses: **Common Function**, not
  "everyone". One name for one thing, everywhere it appears.
- A control says what happens when it is used, and keeps the same name through
  the flow: **Manage IPCRs**, and the page it opens is the IPCR list.
- An empty state is an invitation, not an apology. "Nothing submitted yet —
  sheets appear here the moment an employee sends one for assessment."
- Do not explain what is not on the screen. An administrator with no IPCR of
  their own does not need a card saying so on every visit.
- English, always, including comments.

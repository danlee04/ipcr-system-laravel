@props(['label'])

{{--
    What the links under it are, and why this person can see them.

    Two of the three runs in the sidebar are conditional - the approver's
    inbox and the whole administration block - so without a heading they
    simply appear, and nothing says on whose behalf.

    `collapsed` is only ever expressed through `lg:`. On a phone the sidebar
    is a drawer and always shows labels.
--}}
<p data-nav-group
    class="px-3 pb-1 pt-4 font-data text-[0.625rem] uppercase tracking-[0.18em] text-nav-300 first:pt-1"
    :class="collapsed ? 'lg:hidden' : ''">{{ $label }}</p>

{{-- Collapsed there is no room for the words, so the break itself carries the
     grouping - otherwise the admin icons sit flush against the employee's own
     and the sidebar reads as one long strip. Hidden on the first group, which
     already has the brand bar's border above it. --}}
<div data-nav-rule aria-hidden="true"
    class="mx-3 my-2 hidden border-t border-white/10 first-of-type:lg:hidden"
    :class="collapsed ? 'lg:block' : ''"></div>

{{--
    The standard page wrapper. Use this for every page body instead of hand
    rolling a `max-w-* mx-auto` container.

    It fills the width left over beside the sidebar rather than sitting in a
    narrow centred column, which is what the app wants: tables, item lists and
    forms all get room to breathe. The cap only exists so text lines do not run
    the full span of an ultra-wide monitor.

    Override the padding when a page needs it, e.g. <x-page-container class="py-4">.
--}}
<div {{ $attributes->merge(['class' => 'mx-auto w-full max-w-[110rem] px-4 py-8 sm:px-6 lg:px-8']) }}>
    {{ $slot }}
</div>

{{-- The part a live filter replaces.

     Dimmed while a request is in flight rather than emptied: rows that vanish
     and come back read as a page reload, which is the thing being avoided.
     Everything here is also correct with no JavaScript at all - `busy` is
     simply never true. --}}
<div data-live-results x-bind:aria-busy="busy ? 'true' : 'false'"
    x-bind:class="busy ? 'opacity-50' : ''"
    {{ $attributes->merge(['class' => 'space-y-6 transition-opacity duration-150']) }}>
    {{ $slot }}
</div>

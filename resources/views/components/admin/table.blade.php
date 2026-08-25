@props(['head' => null])

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

@props(['active' => true])

<span
    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $active ? 'bg-emerald-100 text-emerald-800 ring-emerald-500/20' : 'bg-gray-100 text-gray-600 ring-gray-500/20' }}">
    {{ $active ? 'Active' : 'Inactive' }}
</span>

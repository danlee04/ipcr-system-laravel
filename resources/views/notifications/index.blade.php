<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Notifications') }}</h2>

            @if ($unreadCount > 0)
                <form method="POST" action="{{ route('notifications.read') }}">
                    @csrf
                    <button type="submit"
                        class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 transition-colors hover:bg-gray-50">
                        Mark all as read
                    </button>
                </form>
            @endif
        </div>
    </x-slot>

    <x-page-container class="space-y-6">
        <x-admin.flash />

        <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-950/5">
            <ul class="divide-y divide-gray-200">
                @forelse ($notifications as $notification)
                    {{-- Unread carries a mark and a white ground; read fades
                         back rather than disappearing, because the list is
                         also the record of what happened. --}}
                    <li class="{{ $notification->read_at === null ? 'bg-white' : 'bg-gray-50/60' }}">
                        <a href="{{ route('notifications.show', $notification->id) }}"
                            class="flex items-start gap-3 px-6 py-4 transition-colors hover:bg-gray-50">
                            <span
                                class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $notification->read_at === null ? 'bg-nav-900' : 'bg-transparent' }}"
                                aria-hidden="true"></span>

                            <span class="min-w-0 flex-1">
                                <span
                                    class="block text-sm {{ $notification->read_at === null ? 'font-medium text-gray-900' : 'text-gray-600' }}">
                                    {{ $notification->data['message'] ?? 'An IPCR was updated.' }}
                                </span>
                                <span class="mt-0.5 block text-xs text-gray-500">
                                    {{ $notification->created_at->diffForHumans() }}
                                    @if ($notification->read_at === null)
                                        &middot; <span class="font-medium text-nav-900">Unread</span>
                                    @endif
                                </span>
                            </span>

                            <span class="shrink-0 self-center text-sm font-medium text-gray-900">Open</span>
                        </a>
                    </li>
                @empty
                    <li class="px-6 py-12 text-center">
                        <p class="text-sm font-medium text-gray-900">Nothing yet</p>
                        <p class="mt-1 text-sm text-gray-500">
                            You will hear from this page when an IPCR reaches you, comes back to you, or is approved.
                        </p>
                    </li>
                @endforelse
            </ul>
        </div>

        {{ $notifications->links() }}
    </x-page-container>
</x-app-layout>

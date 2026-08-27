<?php

namespace App\Http\Controllers;

use App\Models\Ipcr;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * What the signed-in user has been told.
 *
 * Everything is scoped to their own notifications rather than looked up
 * globally and then checked. There is no way to name somebody else's: the
 * query simply does not reach it, so a wrong id is a 404 rather than a
 * decision.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $request->user()->notifications()->paginate(20),
            'unreadCount'   => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * Open one: mark it read and go to what it is about.
     *
     * Reading it is the act of opening it, so nobody has to tick anything off
     * a list they have already dealt with.
     */
    public function show(Request $request, string $notification): RedirectResponse
    {
        $row = $request->user()->notifications()->findOrFail($notification);

        $row->markAsRead();

        $ipcrId = $row->data['ipcr_id'] ?? null;

        // The IPCR may have been deleted since - a draft its owner scrapped.
        // The notification outlives it, and it should not lead to a 404.
        return $ipcrId !== null && Ipcr::whereKey($ipcrId)->exists()
            ? redirect()->route('ipcrs.show', $ipcrId)
            : redirect()->route('notifications.index')
                ->with('error', 'That IPCR no longer exists.');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'Marked everything as read.');
    }
}

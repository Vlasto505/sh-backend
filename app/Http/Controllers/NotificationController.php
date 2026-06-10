<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $notifications = $request->user()->notifications()->paginate(20);

        return Inertia::render('Notifications/Index', [
            'notifications' => [
                'data' => collect($notifications->items())->map(fn ($n) => [
                    'id'         => $n->id,
                    'message'    => $n->data['message'] ?? '',
                    'title'      => $n->data['title'] ?? '',
                    'url'        => $n->data['url'] ?? null,
                    'read'       => $n->read_at !== null,
                    'created_at' => $n->created_at,
                ]),
                'current_page'  => $notifications->currentPage(),
                'last_page'     => $notifications->lastPage(),
                'prev_page_url' => $notifications->previousPageUrl(),
                'next_page_url' => $notifications->nextPageUrl(),
                'total'         => $notifications->total(),
            ],
        ]);
    }

    public function markRead(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'Všetky notifikácie označené ako prečítané.');
    }
}

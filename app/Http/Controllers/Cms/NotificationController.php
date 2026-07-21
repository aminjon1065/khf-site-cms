<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $perPage = min(max((int) $request->integer('per_page', 25), 1), 100);
        $notifications = $user->notifications()->latest()->paginate($perPage)->withQueryString();

        return Inertia::render('notifications/index', [
            'items' => $notifications->getCollection()
                ->map(fn (DatabaseNotification $notification): array => $this->payload($notification))
                ->values()
                ->all(),
            'meta' => [
                'from' => $notifications->firstItem(),
                'to' => $notifications->lastItem(),
                'total' => $notifications->total(),
                'prev' => $notifications->previousPageUrl(),
                'next' => $notifications->nextPageUrl(),
            ],
        ]);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()?->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }

    public function markRead(Request $request, string $id): RedirectResponse
    {
        $request->user()?->notifications()->where('id', $id)->update(['read_at' => now()]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(DatabaseNotification $notification): array
    {
        /** @var array<string, mixed> $data */
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'title' => $data['title'] ?? '',
            'message' => $data['message'] ?? '',
            'tone' => $data['tone'] ?? 'info',
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
            'created_diff' => $notification->created_at?->diffForHumans() ?? '',
            'subject_type' => $data['subject_type'] ?? null,
            'subject_id' => $data['subject_id'] ?? null,
            'url' => $data['url'] ?? null,
        ];
    }
}

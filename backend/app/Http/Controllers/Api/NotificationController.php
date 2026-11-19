<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    /**
     * Menampilkan notifikasi milik pengguna yang login.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'unread' => ['sometimes', 'boolean'],
            'type' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();

        $query = $user->notifications()->latest();

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        if (! empty($validated['type'])) {
            $query->where('data->type', $validated['type']);
        }

        $notifications = $query
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        return response()->json([
            'data' => $notifications
                ->getCollection()
                ->map(fn (DatabaseNotification $notification) =>
                    $this->formatNotification($notification)
                )
                ->values(),

            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],

            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mengambil jumlah notifikasi yang belum dibaca.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => $request->user()
                ->unreadNotifications()
                ->count(),
        ]);
    }

    /**
     * Menandai satu notifikasi sebagai sudah dibaca.
     */
    public function markAsRead(
        Request $request,
        string $notificationId
    ): JsonResponse {
        // Query melalui relasi user agar tidak bisa membaca
        // atau mengubah notifikasi milik pengguna lain.
        $notification = $request->user()
            ->notifications()
            ->whereKey($notificationId)
            ->firstOrFail();

        if ($notification->read_at === null) {
            $notification->markAsRead();
            $notification->refresh();
        }

        return response()->json([
            'message' => 'Notifikasi berhasil ditandai sudah dibaca.',
            'data' => $this->formatNotification($notification),
            'unread_count' => $request->user()
                ->unreadNotifications()
                ->count(),
        ]);
    }

    /**
     * Menandai seluruh notifikasi sebagai sudah dibaca.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();

        $updated = $user->unreadNotifications()->update([
            'read_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Seluruh notifikasi berhasil ditandai sudah dibaca.',
            'updated_count' => $updated,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Menyamakan struktur data notifikasi untuk frontend.
     */
    private function formatNotification(
        DatabaseNotification $notification
    ): array {
        $data = $notification->data ?? [];

        return [
            'id' => $notification->id,
            'type' => $data['type'] ?? 'general',
            'title' => $data['title'] ?? 'Notifikasi',
            'message' => $data['message'] ?? '',
            'severity' => $data['severity'] ?? 'info',
            'data' => $data,
            'read_at' => $notification->read_at?->toISOString(),
            'created_at' => $notification->created_at?->toISOString(),
        ];
    }
}

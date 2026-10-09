<?php

namespace App\Http\Controllers\API\V1\Mobile;

use App\Http\Controllers\API\V1\Mobile\Concerns\BuildsMobilePayloads;
use App\Http\Controllers\API\V1\Mobile\Concerns\InteractsWithMobileUsers;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use BuildsMobilePayloads;
    use InteractsWithMobileUsers;

    public function index(Request $request)
    {
        $customer = $this->currentCustomer($request);

        $notifications = Notification::query()
            ->where('customer_id', $customer->id)
            ->latest('id')
            ->paginate($request->integer('per_page') ?: 20);

        return response()->json([
            'success' => true,
            'data' => collect($notifications->items())
                ->map(fn (Notification $notification) => $this->mobileNotificationPayload($notification))
                ->values(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'last_page' => $notifications->lastPage(),
                'unread_count' => Notification::query()
                    ->where('customer_id', $customer->id)
                    ->whereNull('read_at')
                    ->count(),
            ],
        ]);
    }

    public function markRead(Request $request, string $notification)
    {
        $customer = $this->currentCustomer($request);

        $notificationModel = Notification::query()
            ->where('customer_id', $customer->id)
            ->findOrFail((int) $notification);

        if (! $notificationModel->read_at) {
            $notificationModel->forceFill(['read_at' => now()])->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
            'data' => $this->mobileNotificationPayload($notificationModel->fresh()),
        ]);
    }

    public function markAllRead(Request $request)
    {
        $customer = $this->currentCustomer($request);

        Notification::query()
            ->where('customer_id', $customer->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.',
        ]);
    }
}

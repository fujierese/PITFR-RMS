<?php
namespace App\Http\Controllers;

use App\Models\FacilityRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $notifications = $user->notifications()->paginate(20);
        $requestIds = $notifications->getCollection()
            ->map(fn ($notification) => $notification->data['request_id'] ?? null)
            ->filter(fn ($id) => is_numeric($id))
            ->unique()
            ->values();
        $requestDetails = $requestIds->isEmpty()
            ? collect()
            : FacilityRequest::with('requester')->whereIn('id', $requestIds)->get()->keyBy('id');

        return view('notifications.index', compact('notifications', 'requestDetails'));
    }

    public function markAsRead(Request $request, $id)
    {
        $notification = Auth::user()->notifications()->where('id', $id)->firstOrFail();

        if (!$notification->read_at) {
            $notification->markAsRead();
        }

        $unreadCount = Auth::user()->unreadNotifications()->count();

        return response()->json([
            'success' => true,
            'unread_count' => $unreadCount,
        ]);
    }
}
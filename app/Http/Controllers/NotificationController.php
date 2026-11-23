<?php
namespace App\Http\Controllers;

use App\Models\FacilityRequest;
use App\Models\RevisionHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class NotificationController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $notifications = $user->notifications()->paginate(20);
        $requestIds = $notifications->getCollection()
            ->map(fn ($notification) => $notification->data['request_id'] ?? $notification->data['facility_request_id'] ?? null)
            ->filter(fn ($id) => is_numeric($id))
            ->unique()
            ->values();
        $requestDetails = $requestIds->isEmpty()
            ? collect()
            : FacilityRequest::with('requester')->whereIn('id', $requestIds)->get()->keyBy('id');

        $unreadCount = $user->unreadNotifications()->count();

        return view('notifications.index', compact('notifications', 'requestDetails', 'unreadCount'));
    }

    /**
     * QA-3: opening a notification marks it read and goes to what it is about. A
     * suggested new schedule opens the request at the suggestion, where the requestor
     * can accept or decline it. Requests store their ID under "request_id" or (for
     * schedule changes) "facility_request_id"; the page used to read only the first,
     * so schedule-change notifications led back to the notifications list. A request
     * that is gone, no longer visible to the user, or a suggestion already answered
     * gets a clear message instead.
     */
    public function open(string $id)
    {
        $notification = Auth::user()->notifications()->where('id', $id)->first();
        if (! $notification) {
            return redirect()->route('notifications.index')
                ->with('warning', 'That notification could not be found. It may have been removed.');
        }
        if (! $notification->read_at) {
            $notification->markAsRead();
        }

        $data = is_array($notification->data) ? $notification->data : [];
        $requestId = $data['request_id'] ?? $data['facility_request_id'] ?? null;
        $reference = $data['control_number'] ?? null;

        if (! is_numeric($requestId)) {
            $route = is_string($data['route'] ?? null) ? $data['route'] : null;
            if ($route && parse_url($route, PHP_URL_HOST) === request()->getHost()) {
                return redirect()->to($route);
            }

            return redirect()->route('notifications.index');
        }

        $facilityRequest = FacilityRequest::withTrashed()->find((int) $requestId);
        $label = $reference ?? $facilityRequest?->control_number ?? 'this request';
        if (! $facilityRequest || $facilityRequest->trashed()) {
            return redirect()->route('notifications.index')
                ->with('warning', "Request {$label} is no longer available. It may have been deleted.");
        }
        if (Gate::denies('view', $facilityRequest)) {
            return redirect()->route('notifications.index')
                ->with('warning', "You no longer have access to request {$label}.");
        }

        $destination = route('request.show', $facilityRequest);
        if (! empty($data['revision_id'])) {
            $revision = RevisionHistory::find((int) $data['revision_id']);
            if (! $revision || (int) $revision->facility_request_id !== (int) $facilityRequest->id) {
                return redirect()->to($destination)
                    ->with('warning', 'This suggested new schedule no longer exists. The request details below are up to date.');
            }
            if ($revision->status !== 'pending') {
                $outcome = match ($revision->status) {
                    'approved', 'accepted' => 'accepted',
                    'rejected', 'declined' => 'declined',
                    'expired' => 'expired without a response',
                    'cancelled' => 'withdrawn',
                    default => 'already answered',
                };

                return redirect()->to($destination)
                    ->with('warning', "This suggested new schedule was {$outcome}. The request details below are up to date.");
            }

            return redirect()->to($destination . '#schedule-proposal');
        }

        return redirect()->to($destination);
    }

    /**
     * QA 11-21 #13: Mark as Read. Only the signed-in user's own notification can be
     * changed (another user's ID is "not found"). The update is a single conditional
     * write, so repeated or simultaneous clicks are harmless. The notification stays
     * in the list and still opens its request (and any suggested schedule).
     */
    public function markAsRead(Request $request, string $id)
    {
        $user = Auth::user();
        $notification = $user->notifications()->where('id', $id)->first();

        if (! $notification) {
            return $this->readResponse($request, false, 'That notification could not be found. It may have been removed.', 404);
        }

        $user->notifications()->where('id', $id)->whereNull('read_at')->update(['read_at' => now()]);

        return $this->readResponse($request, true, 'Notification marked as read.', 200, [
            'id' => $notification->id,
            'status' => 'read',
        ]);
    }

    /** QA 11-21 #13: Mark All as Read, for the signed-in user only. */
    public function markAllAsRead(Request $request)
    {
        $marked = Auth::user()->unreadNotifications()->update(['read_at' => now()]);

        $message = $marked > 0
            ? ($marked === 1 ? '1 notification marked as read.' : "{$marked} notifications marked as read.")
            : 'All notifications are already read.';

        return $this->readResponse($request, true, $message, 200, ['marked' => $marked]);
    }

    private function readResponse(Request $request, bool $success, string $message, int $status, array $extra = [])
    {
        $unreadCount = Auth::user()->unreadNotifications()->count();

        if ($request->expectsJson()) {
            return response()->json(array_merge([
                'success' => $success,
                'message' => $message,
                'unread_count' => $unreadCount,
            ], $extra), $status);
        }

        return redirect()->back(fallback: route('notifications.index'))
            ->with($success ? 'success' : 'warning', $message);
    }
}
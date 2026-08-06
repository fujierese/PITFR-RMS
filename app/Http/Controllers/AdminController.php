<?php
namespace App\Http\Controllers;

use App\Models\FacilityRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        return app(SupplyOfficeController::class)->index($request);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'id'       => 'required|integer|exists:facility_requests,id',
            'action'   => 'required|in:approve,reject',
            'notes'    => 'nullable|string',
            'priority' => 'nullable|in:regular,institutional',
        ]);

        DB::beginTransaction();

        try {
            $fr = FacilityRequest::whereKey($validated['id'])->lockForUpdate()->firstOrFail();

            if ($fr->status === 'approved' && $validated['action'] === 'approve') {
                DB::rollBack();
                return back()->with('info', 'This request has already been approved.');
            }

            if ($fr->status === 'rejected' && $validated['action'] === 'reject') {
                DB::rollBack();
                return back()->with('info', 'This request has already been rejected.');
            }

            if ($fr->venue_status !== 'approved' || $fr->equipment_status !== 'approved') {
                DB::rollBack();
                return back()->withErrors(['action' => 'Cannot approve: custodians have not yet approved.']);
            }

            // Map action to correct enum value
            $statusValue = $validated['action'] === 'approve' ? 'approved' : 'rejected';

            $updates = [
                'status'        => $statusValue,  // ← 'approved' or 'rejected'
                'approved_by'   => Auth::user()->name,
                'approved_by_id' => Auth::id(),
                'notes'         => $validated['notes'] ?? '',
                'approved_date' => now(),
            ];

            if (!empty($validated['priority'] ?? null)) {
                $updates['priority'] = $validated['priority'];
            }

            $fr->update($updates);

            $fr->addHistory($statusValue,
                'Admin ' . Auth::user()->name . ' completed request as ' . $statusValue .
                (($validated['priority'] ?? null) ? ' with priority ' . $validated['priority'] : ''),
                Auth::user()->id);

            DB::commit();

            // Notify the requestor
        $requester = \App\Models\User::find($fr->requested_by_id);
        if ($requester) {
            $requester->notify(new \App\Notifications\RequestStatusChanged(
                $fr,
                $validated['action'],
                $validated['notes'] ?? ''
            ));
        }

        $label = $validated['action'] === 'approve' ? 'approved' : 'rejected';
        return redirect()->route('supply-office.index')
                        ->with('success', "Request {$label} successfully.");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Admin update failed for request ' . ($validated['id'] ?? 'unknown') . ': ' . $e->getMessage(), ['exception' => $e]);
            return back()->withErrors('Unable to process the request at this time.');
        }
    }

    public function destroy(Request $request)
    {
        FacilityRequest::findOrFail($request->input('id'))->delete();
        return redirect()->route('supply-office.index')->with('success', 'Request deleted successfully.');
    }

    public function finalApproval(Request $request)
    {
        $finalApprovalQueue = FacilityRequest::with('requester')
            ->where('status', 'pending')
            ->where(function ($query) {
                $query->where('venue_status', '!=', 'rejected')
                    ->orWhere('equipment_status', '!=', 'rejected');
            })
            ->orderByDesc('created_at')
            ->get();

        return view('supply-office.final-approval', [
            'finalApprovalQueue' => $finalApprovalQueue,
            'pendingFinalApprovalCount' => $finalApprovalQueue->count(),
        ]);
    }

    public function users(Request $request)
    {
        $users = User::orderBy('name')->get();
        $editUserId = (int) $request->get('edit_user', 0);

        return view('supply-office.users', [
            'users' => $users,
            'editUserId' => $editUserId,
        ]);
    }

    public function updateUser(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username,' . $user->id],
            'role' => ['required', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'requestor_type' => ['nullable', 'string', 'max:255'],
            'school_id_number' => ['nullable', 'string', 'max:255'],
            'office_or_organization' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:255'],
        ]);

        $user->fill($validated);
        $user->save();

        return redirect()->route('admin.users')->with('success', 'User updated successfully.');
    }

    public function destroyUser(User $user)
    {
        $user->delete();

        return redirect()->route('admin.users')->with('success', 'User deleted successfully.');
    }

    public function reports(Request $request)
    {
        return app(SupplyOfficeController::class)->usageReports($request);
    }

    public function settings(Request $request)
    {
        $user = Auth::user();

        return view('supply-office.settings', [
            'user' => $user,
            'appName' => config('app.name'),
            'appEnv' => config('app.env'),
            'appUrl' => config('app.url'),
            'maintenanceMode' => app()->isDownForMaintenance(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:255'],
            'office_or_organization' => ['nullable', 'string', 'max:255'],
            'school_id_number' => ['nullable', 'string', 'max:255'],
        ]);

        $user->fill($validated);
        $user->save();

        return redirect()->route('supply-office.settings')->with('success', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user = Auth::user();
        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Your current password is incorrect.']);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return redirect()->route('supply-office.settings')->with('success', 'Password updated successfully.');
    }

    public function calendar(Request $request)
    {
        $requests = FacilityRequest::where('venue_status', 'approved')
            ->where('equipment_status', 'approved')
            ->orderBy('start_date')
            ->get();

        $calendarItems = [];
        foreach ($requests as $req) {
            $start = $req->start_date;
            $end = $req->end_date ?: $req->start_date;

            $current = $start->copy();
            while ($current->lte($end)) {
                $key = $current->toDateString();
                $calendarItems[$key][] = $req;
                $current->addDay();
            }
        }

        return view('supply-office.calendar', [
            'calendarItems' => $calendarItems,
        ]);
    }

    public function auditLogs(Request $request)
    {
        $query = \App\Models\RequestHistory::with(['facilityRequest', 'user'])
            ->orderByDesc('occurred_at');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', '%' . $search . '%')
                  ->orWhere('detail', 'like', '%' . $search . '%')
                  ->orWhereHas('facilityRequest', function ($fr) use ($search) {
                      $fr->where('control_number', 'like', '%' . $search . '%')
                         ->orWhere('name_of_activity', 'like', '%' . $search . '%');
                  })
                  ->orWhereHas('user', function ($u) use ($search) {
                      $u->where('name', 'like', '%' . $search . '%');
                  });
            });
        }

        if ($action = $request->get('action')) {
            $query->where('action', $action);
        }

        if ($dateFrom = $request->get('date_from')) {
            $query->whereDate('occurred_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->get('date_to')) {
            $query->whereDate('occurred_at', '<=', $dateTo);
        }

        $auditLogs = $query->paginate(50);

        return view('supply-office.audit-logs', [
            'auditLogs' => $auditLogs,
            'filters' => $request->only(['search', 'action', 'date_from', 'date_to']),
        ]);
    }
}
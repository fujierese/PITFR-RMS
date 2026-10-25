<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesAccountSettings;
use App\Models\FacilityRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class IgpController extends Controller
{
    use ManagesAccountSettings;

    public function index()
    {
        $requests = FacilityRequest::query()
            ->with(['requester', 'reservationSchedule', 'paymentRecorder', 'refundRecorder'])
            ->upcomingReservation()
            ->where('supply_office_review_status', 'confirmed')
            ->whereHas('requester', fn ($query) => $query->where('requestor_type', 'outsider'))
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->orderBy('start_date')
            ->orderBy('start_time')
            ->paginate(20);

        return view('igp.index', compact('requests'));
    }

    public function settings()
    {
        return view('igp.settings', [
            'user' => Auth::user(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'surname' => ['nullable', 'string', 'max:100'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:50'],
            'contact_number' => ['nullable', 'string', 'max:255'],
        ]);

        $user->fill($validated);
        $user->name = User::formatFullName(
            $validated['surname'] ?? $user->surname,
            $validated['first_name'] ?? $user->first_name,
            $validated['middle_name'] ?? $user->middle_name,
            $validated['suffix'] ?? $user->suffix,
        );
        $user->save();

        return redirect()->route('igp.settings')->with('success', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);
        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Your current password is incorrect.']);
        }

        $user->password = Hash::make($validated['password']);
        $user->save();
        $user->tokens()->delete();
        $this->notifyPasswordChanged($user);

        return redirect()->route('igp.settings')->with('success', 'Password updated successfully.');
    }

    public function updateNotificationPreferences(Request $request)
    {
        return $this->saveNotificationPreferences($request, 'igp.settings');
    }

    public function updateSignature(Request $request)
    {
        return $this->saveSignature($request, 'igp.settings');
    }

    public function recordPayment(Request $request, FacilityRequest $facilityRequest)
    {
        $validated = $request->validate([
            'igp_receipt_number' => ['required', 'string', 'max:100'],
        ]);
        $igpUserId = Auth::user()->getKey();

        DB::transaction(function () use ($facilityRequest, $validated, $igpUserId): void {
            $lockedRequest = FacilityRequest::query()->lockForUpdate()->findOrFail($facilityRequest->id);
            abort_unless(
                $lockedRequest->requester?->requestor_type === 'outsider'
                    && $lockedRequest->supply_office_review_status === 'confirmed'
                    && ! in_array($lockedRequest->status, ['rejected', 'cancelled'])
                    && $lockedRequest->cancellation_status !== 'confirmed',
                409,
                'Payment can only be recorded for an outsider reservation confirmed as available by the Supply Office.'
            );
            abort_if($lockedRequest->igp_payment_status === 'paid', 409, 'Payment has already been recorded.');

            $lockedRequest->update([
                'igp_receipt_number' => trim($validated['igp_receipt_number']),
                'igp_payment_status' => 'paid',
                'igp_paid_by_id' => $igpUserId,
                'igp_paid_at' => now(),
            ]);
            $lockedRequest->addHistory(
                'igp_payment_recorded',
                'IGP recorded payment using receipt ' . trim($validated['igp_receipt_number']) . '.',
                $igpUserId
            );
        });

        return back()->with('success', 'Payment recorded.');
    }

    public function recordRefund(Request $request, FacilityRequest $facilityRequest)
    {
        $validated = $request->validate([
            'igp_refund_reference' => ['required', 'string', 'max:100'],
            'igp_refund_notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $igpUserId = Auth::user()->getKey();

        DB::transaction(function () use ($facilityRequest, $validated, $igpUserId): void {
            $lockedRequest = FacilityRequest::query()->lockForUpdate()->findOrFail($facilityRequest->id);
            abort_unless(
                $lockedRequest->requester?->requestor_type === 'outsider'
                    && $lockedRequest->igp_payment_status === 'paid'
                    && $lockedRequest->cancellation_status === 'confirmed',
                409,
                'A refund can only be recorded for a paid reservation whose cancellation was confirmed by the Supply Office.'
            );
            abort_if($lockedRequest->igp_refund_status === 'refunded', 409, 'A refund has already been recorded.');

            $lockedRequest->update([
                'igp_refund_status' => 'refunded',
                'igp_refund_reference' => trim($validated['igp_refund_reference']),
                'igp_refund_notes' => $validated['igp_refund_notes'] ?? null,
                'igp_refunded_by_id' => $igpUserId,
                'igp_refunded_at' => now(),
            ]);
            $lockedRequest->addHistory(
                'igp_refund_recorded',
                'IGP recorded refund reference ' . trim($validated['igp_refund_reference']) . '.',
                $igpUserId
            );
        });

        return back()->with('success', 'Refund recorded.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Decision D4: the Supply Office reviews uploaded IDs. The image is deleted after
 * every decision; only the ID type, the result and who decided are kept.
 */
class AccountVerificationController extends Controller
{
    public function index()
    {
        abort_unless(User::verificationEnabled(), 404);

        $pending = User::query()
            ->where('verification_status', 'pending')
            ->orderBy('verification_submitted_at')
            ->get();

        $recent = User::query()
            ->whereIn('verification_status', ['verified', 'rejected'])
            ->whereNotNull('verification_method')
            ->whereNotNull('verified_by_id')
            ->latest('verified_at')
            ->limit(20)
            ->get();

        return view('supply-office.verifications', compact('pending', 'recent'));
    }

    /** Streams the private ID image to the Supply Office; every view is logged. */
    public function file(User $user)
    {
        abort_unless(User::verificationEnabled() && $user->verification_id_file, 404);
        abort_unless(Storage::disk('local')->exists($user->verification_id_file), 404);

        AuditLog::create([
            'actor_id' => Auth::user()->id,
            'target_user_id' => $user->id,
            'action' => 'verification_id_viewed',
            'details' => 'Viewed the uploaded ID for account verification.',
            'old_values' => [],
            'new_values' => [],
        ]);

        return Storage::disk('local')->response($user->verification_id_file, null, [
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ]);
    }

    public function decide(Request $request, User $user)
    {
        abort_unless(User::verificationEnabled(), 404);

        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'requestor_type' => ['nullable', 'in:' . implode(',', User::CLAIMABLE_REQUESTOR_TYPES)],
            'reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:500'],
        ], [
            'reason.required_if' => 'Give the reason so the requestor knows what to fix.',
        ]);

        $actor = Auth::user();
        $result = DB::transaction(function () use ($user, $data, $actor): ?string {
            $locked = User::query()->lockForUpdate()->find($user->id);
            if (! $locked || $locked->verification_status !== 'pending') {
                return null;
            }

            $file = $locked->verification_id_file;
            $old = ['requestor_type' => $locked->requestor_type, 'verification_status' => $locked->verification_status];

            if ($data['decision'] === 'approve') {
                // The Supply Office may correct the category (e.g. a student ID uploaded as "faculty").
                $type = $data['requestor_type'] ?? $locked->claimed_requestor_type ?? $locked->requestor_type;
                $locked->forceFill([
                    // Verified accounts get a position for their requests (sign-up does not ask for one).
                    'position' => trim((string) $locked->position) !== '' ? $locked->position : User::defaultPositionFor($type),
                    'requestor_type' => $type,
                    'verification_status' => 'verified',
                    'verification_note' => null,
                ]);
            } else {
                $locked->forceFill([
                    'verification_status' => 'rejected',
                    'verification_note' => Str::limit(trim((string) $data['reason']), 500, ''),
                ]);
            }

            $locked->forceFill([
                'verification_id_file' => null,
                'verified_at' => now(),
                'verified_by_id' => $actor->id,
            ])->save();

            AuditLog::create([
                'actor_id' => $actor->id,
                'target_user_id' => $locked->id,
                'action' => $data['decision'] === 'approve' ? 'user_verified' : 'user_verification_rejected',
                'details' => $data['decision'] === 'approve'
                    ? 'Verified ' . ($locked->verificationIdTypeLabel() ?? 'ID') . '; category ' . $locked->requestor_type . '. ID image deleted.'
                    : 'Rejected ID: ' . $locked->verification_note . '. ID image deleted.',
                'old_values' => $old,
                'new_values' => ['requestor_type' => $locked->requestor_type, 'verification_status' => $locked->verification_status],
            ]);

            return $file ?: '';
        });

        if ($result === null) {
            return back()->with('info', 'This account was already reviewed.');
        }
        if ($result !== '') {
            Storage::disk('local')->delete($result);
        }

        return back()->with('success', $data['decision'] === 'approve'
            ? "{$user->name} is verified. The ID image was deleted."
            : "{$user->name}'s ID was rejected. They can upload a new one from Account Settings.");
    }

    /** Requestor uploads (or re-uploads) an ID from Account Settings. */
    public function submit(Request $request)
    {
        abort_unless(User::verificationEnabled(), 404);

        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->role === 'requestor', 403);
        if (in_array($user->verification_status, ['verified', 'pending'], true)) {
            return back()->with('info', $user->verification_status === 'verified'
                ? 'Your account is already verified.'
                : 'Your ID is already waiting for review.');
        }

        $data = $request->validate([
            'requestor_type' => ['required', 'in:' . implode(',', User::CLAIMABLE_REQUESTOR_TYPES)],
            'verification_id_type' => ['required', 'in:' . implode(',', array_keys(User::VERIFICATION_ID_TYPES))],
            'verification_id' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $upload = $request->file('verification_id');
        $path = $upload->storeAs('verification-ids', Str::uuid() . '.' . strtolower($upload->getClientOriginalExtension()), 'local');

        $user->forceFill([
            'claimed_requestor_type' => $data['requestor_type'],
            'verification_status' => 'pending',
            'verification_method' => 'id_upload',
            'verification_id_type' => $data['verification_id_type'],
            'verification_id_file' => $path,
            'verification_note' => null,
            'verification_submitted_at' => now(),
        ])->save();

        return back()->with('success', 'Your ID was sent to the Supply Office for review.');
    }
}

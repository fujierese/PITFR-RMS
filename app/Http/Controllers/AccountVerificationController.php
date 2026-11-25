<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\AccountVerificationDocuments as Documents;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Identity and Affiliation Verification (combined review). The Supply Office sees the ID
 * and the COR / proof of affiliation together and makes ONE decision:
 *   Approve Account                — identity and affiliation verified, category applied
 *   Request Additional Documents   — account stays pending; the requestor uploads again
 *   Reject Application             — reason required
 * The files are deleted after every decision; the types, period, result, reviewer and
 * time are kept in the account and the audit log.
 */
class AccountVerificationController extends Controller
{
    public const DECISION_ACTIONS = [
        'user_verified' => 'Approved',
        'user_verification_documents_requested' => 'Additional documents requested',
        'user_verification_rejected' => 'Rejected',
    ];

    public function index()
    {
        abort_unless(User::verificationEnabled(), 404);

        $pending = User::query()
            ->where('verification_status', 'pending')
            ->orderBy('verification_submitted_at')
            ->get();

        $awaitingRequestor = User::query()
            ->where('verification_status', 'documents_requested')
            ->latest('verified_at')
            ->limit(50)
            ->get();

        $history = AuditLog::query()
            ->with(['actor:id,name', 'targetUser:id,name,username'])
            ->whereIn('action', array_keys(self::DECISION_ACTIONS))
            ->latest('id')
            ->limit(30)
            ->get();

        return view('supply-office.verifications', compact('pending', 'awaitingRequestor', 'history'));
    }

    /** Streams the private ID to the Supply Office; every view is logged. */
    public function file(User $user)
    {
        return $this->stream($user, 'verification_id_file', 'verification_id_viewed', 'Viewed the uploaded ID for account verification.');
    }

    /** Streams the private COR / proof of affiliation; every view is logged. */
    public function affiliationFile(User $user)
    {
        abort_unless(Documents::enabled(), 404);

        return $this->stream($user, 'verification_affiliation_file', 'verification_affiliation_viewed', 'Viewed the uploaded affiliation document for account verification.');
    }

    private function stream(User $user, string $column, string $action, string $details)
    {
        abort_unless(User::verificationEnabled() && $user->{$column}, 404);
        abort_unless(Storage::disk('local')->exists($user->{$column}), 404);

        AuditLog::create([
            'actor_id' => Auth::user()->id,
            'target_user_id' => $user->id,
            'action' => $action,
            'details' => $details,
            'old_values' => [],
            'new_values' => [],
        ]);

        return Storage::disk('local')->response($user->{$column}, null, [
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ]);
    }

    public function decide(Request $request, User $user)
    {
        abort_unless(User::verificationEnabled(), 404);

        $data = $request->validate([
            'decision' => ['required', 'in:approve,request_documents,reject'],
            'requestor_type' => ['nullable', 'in:' . implode(',', User::CLAIMABLE_REQUESTOR_TYPES)],
            'reason' => ['required_unless:decision,approve', 'nullable', 'string', 'max:500'],
            'documents_checked' => ['accepted_if:decision,approve'],
        ], [
            'reason.required_unless' => 'Give the reason so the requestor knows what to send or fix.',
            'documents_checked.accepted_if' => 'Confirm that you checked both the identity and the affiliation documents.',
        ]);

        $actor = Auth::user();
        $outcome = DB::transaction(function () use ($user, $data, $actor): array {
            $locked = User::query()->lockForUpdate()->find($user->id);
            if (! $locked || $locked->verification_status !== 'pending') {
                return ['state' => 'stale'];
            }

            $type = Documents::normalizeType($data['requestor_type'] ?? $locked->claimed_requestor_type ?? $locked->requestor_type);
            if ($data['decision'] === 'approve' && ($problem = $this->approvalProblem($locked, $type))) {
                return ['state' => 'blocked', 'message' => $problem];
            }

            $files = [$locked->verification_id_file, Documents::enabled() ? $locked->verification_affiliation_file : null];
            $old = ['requestor_type' => $locked->requestor_type, 'verification_status' => $locked->verification_status];
            $reason = Str::limit(trim((string) ($data['reason'] ?? '')), 500, '');

            $locked->forceFill(match ($data['decision']) {
                'approve' => [
                    // Verified accounts get a position for their requests (sign-up does not ask for one).
                    'position' => trim((string) $locked->position) !== '' ? $locked->position : User::defaultPositionFor($type),
                    'requestor_type' => $type,
                    'verification_status' => 'verified',
                    'verification_note' => null,
                ],
                'request_documents' => ['verification_status' => 'documents_requested', 'verification_note' => $reason],
                default => ['verification_status' => 'rejected', 'verification_note' => $reason],
            });
            $locked->forceFill(['verification_id_file' => null, 'verified_at' => now(), 'verified_by_id' => $actor->id]);
            if (Documents::enabled()) {
                $locked->forceFill(['verification_affiliation_file' => null]);
            }
            $locked->save();

            $documents = trim(($locked->verificationIdTypeLabel() ?? 'ID')
                . ($locked->verificationAffiliationTypeLabel() ? ' + ' . $locked->verificationAffiliationTypeLabel() : '')
                . ($locked->verification_document_period ? ' (' . $locked->verification_document_period . ')' : ''));
            AuditLog::create([
                'actor_id' => $actor->id,
                'target_user_id' => $locked->id,
                'action' => match ($data['decision']) {
                    'approve' => 'user_verified',
                    'request_documents' => 'user_verification_documents_requested',
                    default => 'user_verification_rejected',
                },
                'details' => match ($data['decision']) {
                    'approve' => 'Identity and affiliation verified: ' . $documents . '; category ' . $locked->requestor_type . '. Documents deleted.',
                    'request_documents' => 'Additional documents requested: ' . $reason . ' (reviewed ' . $documents . '). Documents deleted.',
                    default => 'Application rejected: ' . $reason . ' (reviewed ' . $documents . '). Documents deleted.',
                },
                'old_values' => $old,
                'new_values' => ['requestor_type' => $locked->requestor_type, 'verification_status' => $locked->verification_status],
            ]);

            return ['state' => 'done', 'files' => $files];
        });

        if ($outcome['state'] === 'stale') {
            return back()->with('info', 'This account was already reviewed.');
        }
        if ($outcome['state'] === 'blocked') {
            return back()->withErrors(['decision' => $outcome['message']]);
        }
        Documents::deleteFiles($outcome['files']);

        return back()->with('success', match ($data['decision']) {
            'approve' => "{$user->name}'s account is approved. The documents were deleted.",
            'request_documents' => "Additional documents were requested from {$user->name}. The account stays pending.",
            default => "{$user->name}'s application was rejected.",
        });
    }

    /** PIT members need the matching ID and a current COR / proof of affiliation; a PIT ID alone is not enough. */
    private function approvalProblem(User $account, string $type): ?string
    {
        if (! $account->verification_id_file) {
            return 'There is no ID to review. Request the documents again.';
        }
        if (! Documents::isMember($type)) {
            return null;
        }
        if (! Documents::enabled() || ! $account->verification_affiliation_file) {
            return 'A PIT ID alone does not prove current affiliation. Request the ' . ($type === 'student' ? 'Certificate of Registration' : 'proof of current affiliation') . '.';
        }
        $isCor = $account->verification_affiliation_type === 'cor';
        if (($type === 'student') !== $isCor) {
            return 'The affiliation document does not match a ' . ucfirst($type) . ' account. Choose the matching category or request the right document.';
        }

        return null;
    }

    /** Requestor uploads (or re-uploads) the documents from Account Settings. */
    public function submit(Request $request)
    {
        abort_unless(User::verificationEnabled(), 404);

        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->role === 'requestor', 403);
        if (in_array($user->verification_status, ['verified', 'pending'], true)) {
            return back()->with('info', $user->verification_status === 'verified'
                ? 'Your account is already verified.'
                : 'Your documents are already waiting for review.');
        }

        $request->validate(['requestor_type' => ['required', 'in:' . implode(',', User::CLAIMABLE_REQUESTOR_TYPES)]]);
        $type = Documents::normalizeType($request->input('requestor_type'));
        $request->validate(Documents::rules($type), Documents::messages());

        $documents = Documents::storeFor($request, $type);
        try {
            $user->forceFill($documents['columns'])->save();
        } catch (\Throwable $exception) {
            Documents::deleteFiles($documents['files']);
            throw $exception;
        }

        AuditLog::create([
            'actor_id' => $user->id,
            'target_user_id' => $user->id,
            'action' => 'verification_documents_submitted',
            'details' => 'Submitted identity and affiliation documents for review (' . $type . ').',
            'old_values' => [],
            'new_values' => ['verification_status' => 'pending'],
        ]);

        return back()->with('success', 'Your documents were sent to the Supply Office for review.');
    }
}

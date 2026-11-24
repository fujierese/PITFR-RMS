<?php

namespace App\Services;

use App\Models\ActivityProposalDeadlineChange;
use App\Models\ActivityProposalVersion;
use App\Models\FacilityRequest;
use App\Models\User;
use App\Notifications\ActivityProposalNotice;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Additional Part 2: Activity Proposal submission, verification and compliance.
 *
 * Document status (from the latest version): not_submitted, draft_submitted,
 * for_verification, requires_correction, verified.
 * Compliance status: no_deadline, within_deadline, overdue.
 *
 * Fully independent of the reservation workflow: nothing here changes the
 * reservation status, approvals or schedule.
 */
class ActivityProposals
{
    public const DOCUMENT_LABELS = [
        'not_submitted' => 'Not Submitted',
        'draft_submitted' => 'Draft Submitted',
        'for_verification' => 'For Verification',
        'requires_correction' => 'Requires Correction',
        'verified' => 'Verified',
    ];

    public const COMPLIANCE_LABELS = [
        'no_deadline' => 'No Deadline Assigned',
        'within_deadline' => 'Within Deadline',
        'overdue' => 'Overdue',
    ];

    public static function enabled(): bool
    {
        return Schema::hasTable('activity_proposal_versions');
    }

    /** @return Collection<int, ActivityProposalVersion> newest first */
    public function versions(FacilityRequest $request): Collection
    {
        if (! self::enabled()) {
            return collect();
        }

        return ActivityProposalVersion::query()
            ->where('facility_request_id', $request->id)
            ->with(['uploadedBy', 'reviewedBy'])
            ->orderByDesc('version_number')
            ->get();
    }

    public function latest(FacilityRequest $request): ?ActivityProposalVersion
    {
        return self::enabled()
            ? ActivityProposalVersion::query()->where('facility_request_id', $request->id)->orderByDesc('version_number')->first()
            : null;
    }

    public function documentStatus(FacilityRequest $request, ?ActivityProposalVersion $latest = null): string
    {
        $latest ??= $this->latest($request);
        if (! $latest) {
            return 'not_submitted';
        }
        if (! $latest->isFinal()) {
            return 'draft_submitted';
        }

        return match ($latest->review_status) {
            'verified' => 'verified',
            'requires_correction' => 'requires_correction',
            default => 'for_verification',
        };
    }

    /** Overdue only when nothing acceptable was submitted by the end of the deadline day. */
    public function complianceStatus(FacilityRequest $request, ?string $documentStatus = null, ?Carbon $now = null): string
    {
        $documentStatus ??= $this->documentStatus($request);
        if (in_array($documentStatus, ['verified', 'for_verification'], true)) {
            return $request->proposal_deadline ? 'within_deadline' : ($documentStatus === 'verified' ? 'within_deadline' : 'no_deadline');
        }
        if (! $request->proposal_deadline) {
            return 'no_deadline';
        }
        $now = ($now ?? Carbon::now())->copy()->setTimezone(config('app.timezone'));

        return $now->greaterThan(Carbon::parse($request->proposal_deadline->toDateString(), config('app.timezone'))->endOfDay())
            ? 'overdue'
            : 'within_deadline';
    }

    /** @return array{document: string, document_label: string, compliance: string, compliance_label: string, latest: ?ActivityProposalVersion} */
    public function summary(FacilityRequest $request): array
    {
        $latest = $this->latest($request);
        $document = $this->documentStatus($request, $latest);
        $compliance = $this->complianceStatus($request, $document);

        return [
            'document' => $document,
            'document_label' => self::DOCUMENT_LABELS[$document],
            'compliance' => $compliance,
            'compliance_label' => self::COMPLIANCE_LABELS[$compliance],
            'latest' => $latest,
        ];
    }

    // ── Working days ─────────────────────────────────────────────────────────

    public static function subtractWorkingDays(Carbon $date, int $days): Carbon
    {
        $result = $date->copy()->startOfDay();
        while ($days > 0) {
            $result->subDay();
            if (! $result->isWeekend()) {
                $days--;
            }
        }

        return $result;
    }

    public static function addWorkingDays(Carbon $date, int $days): Carbon
    {
        $result = $date->copy()->startOfDay();
        while ($days > 0) {
            $result->addDay();
            if (! $result->isWeekend()) {
                $days--;
            }
        }

        return $result;
    }

    /** Working days from $from to $to (positive when $to is later). */
    public static function workingDaysBetween(Carbon $from, Carbon $to): int
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();
        $sign = $to->greaterThanOrEqualTo($from) ? 1 : -1;
        [$a, $b] = $sign === 1 ? [$from, $to] : [$to, $from];
        $count = 0;
        for ($d = $a->copy(); $d->lessThan($b); $d->addDay()) {
            if (! $d->copy()->addDay()->isWeekend()) {
                $count++;
            }
        }

        return $sign * $count;
    }

    /**
     * Suggested deadline: the configured number of working days before the activity.
     * Null when that date is already today or past (the Supply Office decides).
     */
    public function suggestedDeadline(FacilityRequest $request, ?Carbon $now = null): ?Carbon
    {
        if (! $request->start_date || $request->is_emergency) {
            return null;
        }
        $suggested = self::subtractWorkingDays($request->start_date->copy(), (int) config('pitfr.activity_proposal.default_working_days_before', 3));
        $today = ($now ?? Carbon::now())->copy()->setTimezone(config('app.timezone'))->startOfDay();

        return $suggested->greaterThan($today) ? $suggested : null;
    }

    // ── Actions ──────────────────────────────────────────────────────────────

    /** Record an uploaded file as a new version. */
    public function recordSubmission(FacilityRequest $request, string $fileName, string $type, User $user, ?string $originalName = null): ActivityProposalVersion
    {
        if (! in_array($type, ['draft', 'final'], true)) {
            throw new \InvalidArgumentException('Unknown submission type.');
        }

        return DB::transaction(function () use ($request, $fileName, $type, $user, $originalName): ActivityProposalVersion {
            FacilityRequest::query()->lockForUpdate()->findOrFail($request->id);
            $previous = ActivityProposalVersion::query()->where('facility_request_id', $request->id)->orderByDesc('version_number')->first();
            $wasCorrection = $previous && $previous->review_status === 'requires_correction';
            ActivityProposalVersion::query()
                ->where('facility_request_id', $request->id)
                ->where('review_status', 'pending')
                ->update(['review_status' => 'superseded']);

            $version = ActivityProposalVersion::create([
                'facility_request_id' => $request->id,
                'version_number' => ($previous?->version_number ?? 0) + 1,
                'submission_type' => $type,
                'file_name' => $fileName,
                'original_name' => $originalName,
                'uploaded_by_id' => $user->id,
                'submitted_at' => now(),
                'review_status' => 'pending',
            ]);
            // A direct update: saving the model would rebuild its venue rows.
            FacilityRequest::query()->whereKey($request->id)->update(['activity_proposal_file' => $fileName]);
            $request->setAttribute('activity_proposal_file', $fileName);
            $request->syncOriginalAttribute('activity_proposal_file');
            $request->addHistory(
                'proposal_submitted',
                'Activity proposal version ' . $version->version_number . ' (' . $version->typeLabel() . ') uploaded by ' . $user->name . '.',
                $user->id
            );

            if ($type === 'final') {
                $kind = $wasCorrection ? 'corrected_submitted' : 'final_submitted';
                DB::afterCommit(fn () => $this->notifySupplyOffice($request, $kind, $kind . ':' . $version->id));
            }

            return $version;
        });
    }

    public function verify(FacilityRequest $request, User $admin, ?string $remarks): ActivityProposalVersion
    {
        return $this->review($request, $admin, 'verified', $remarks);
    }

    public function requireCorrection(FacilityRequest $request, User $admin, string $remarks, ?string $newDeadline = null): ActivityProposalVersion
    {
        $version = $this->review($request, $admin, 'requires_correction', $remarks);
        if ($newDeadline) {
            $this->setDeadline($request->fresh(), $newDeadline, $admin, 'Correction deadline: ' . $remarks);
        }

        return $version;
    }

    private function review(FacilityRequest $request, User $admin, string $result, ?string $remarks): ActivityProposalVersion
    {
        return DB::transaction(function () use ($request, $admin, $result, $remarks): ActivityProposalVersion {
            $latest = ActivityProposalVersion::query()->where('facility_request_id', $request->id)->orderByDesc('version_number')->lockForUpdate()->first();
            if (! $latest || ! $latest->isFinal()) {
                throw ValidationException::withMessages(['proposal' => 'Only a submitted Final Copy can be verified or returned for correction. A draft cannot be verified.']);
            }
            if ($latest->review_status !== 'pending') {
                throw ValidationException::withMessages(['proposal' => 'This version was already reviewed. Wait for a new submission.']);
            }
            $latest->update([
                'review_status' => $result,
                'remarks' => $remarks !== null && trim($remarks) !== '' ? trim($remarks) : null,
                'reviewed_by_id' => $admin->id,
                'reviewed_at' => now(),
            ]);
            $request->addHistory(
                $result === 'verified' ? 'proposal_verified' : 'proposal_correction_requested',
                ($result === 'verified' ? 'Activity proposal version ' . $latest->version_number . ' verified by ' : 'Activity proposal version ' . $latest->version_number . ' returned for correction by ')
                    . $admin->name . ($latest->remarks ? '. Remarks: ' . $latest->remarks : '.'),
                $admin->id
            );
            \App\Models\AuditLog::create([
                'actor_id' => $admin->id,
                'action' => 'activity_proposal_' . $result,
                'details' => $request->control_number . ' version ' . $latest->version_number,
                'new_values' => ['version_id' => $latest->id, 'remarks' => $latest->remarks],
            ]);
            $kind = $result === 'verified' ? 'verified' : 'requires_correction';
            DB::afterCommit(fn () => $this->notifyRequestor($request, $kind, $kind . ':' . $latest->id));

            return $latest;
        });
    }

    public function setDeadline(FacilityRequest $request, string $date, ?User $admin, ?string $reason, bool $isDefault = false): ActivityProposalDeadlineChange
    {
        return DB::transaction(function () use ($request, $date, $admin, $reason, $isDefault): ActivityProposalDeadlineChange {
            $request = FacilityRequest::query()->lockForUpdate()->findOrFail($request->id);
            $previous = $request->proposal_deadline;
            $new = Carbon::parse($date, config('app.timezone'))->startOfDay();
            if ($previous && trim((string) $reason) === '') {
                throw ValidationException::withMessages(['reason' => 'Give a reason for changing the deadline.']);
            }
            $kind = $isDefault ? 'default' : ($previous === null ? 'assigned' : ($new->greaterThan($previous) ? 'extended' : 'modified'));
            $change = ActivityProposalDeadlineChange::create([
                'facility_request_id' => $request->id,
                'previous_deadline' => $previous?->toDateString(),
                'new_deadline' => $new->toDateString(),
                'kind' => $kind,
                'reason' => $reason,
                'changed_by_id' => $admin?->id,
            ]);
            FacilityRequest::query()->whereKey($request->id)->update(['proposal_deadline' => $new->toDateString()]);
            $request->setAttribute('proposal_deadline', $new->toDateString());
            $request->syncOriginalAttribute('proposal_deadline');
            $request->addHistory(
                'proposal_deadline_' . ($kind === 'default' ? 'assigned' : $kind),
                'Activity proposal deadline ' . ($previous ? 'changed from ' . $previous->format('M j, Y') . ' to ' : 'set to ') . $new->format('M j, Y')
                    . ($admin ? ' by ' . $admin->name : ' (3 working days before the activity)') . ($reason ? '. Reason: ' . $reason : '.'),
                $admin?->id
            );
            $notice = match ($kind) {
                'extended' => 'deadline_extended',
                'modified' => 'deadline_modified',
                default => 'deadline_assigned',
            };
            DB::afterCommit(fn () => $this->notifyRequestor($request, $notice, $notice . ':' . $change->id));

            return $change;
        });
    }

    /** Supply Office follow-up remark on an outstanding document (kept in history). */
    public function recordFollowUp(FacilityRequest $request, User $admin, string $remarks): void
    {
        $request->addHistory('proposal_followup', 'Supply Office follow-up by ' . $admin->name . ': ' . trim($remarks), $admin->id);
    }

    // ── Reminders ────────────────────────────────────────────────────────────

    /**
     * Send the scheduled reminders that are due today. Safe to run repeatedly: each
     * reminder is claimed once in reservation_reminder_logs.
     *
     * @return int reminders sent
     */
    public function sendReminders(?Carbon $now = null): int
    {
        if (! self::enabled()) {
            return 0;
        }
        $now = ($now ?? Carbon::now())->copy()->setTimezone(config('app.timezone'));
        $today = $now->copy()->startOfDay();
        if ($today->isWeekend()) {
            return 0; // reminders follow working days
        }
        $before = array_map('intval', (array) config('pitfr.activity_proposal.reminder_working_days_before', [3, 1]));
        $repeat = max(1, (int) config('pitfr.activity_proposal.overdue_repeat_working_days', 3));
        $sent = 0;

        $requests = FacilityRequest::query()
            ->whereNotNull('proposal_deadline')
            ->whereIn('status', ['pending', 'approved', 'needs_reschedule'])
            ->with('requester')
            ->get();

        foreach ($requests as $request) {
            try {
                $document = $this->documentStatus($request);
                if (in_array($document, ['verified', 'for_verification'], true)) {
                    continue;
                }
                $deadline = Carbon::parse($request->proposal_deadline->toDateString(), config('app.timezone'))->startOfDay();
                $daysLeft = self::workingDaysBetween($today, $deadline);
                $deadlineKey = $deadline->toDateString();

                if ($daysLeft > 0 && in_array($daysLeft, $before, true)) {
                    $type = $daysLeft === min($before) ? 'proposal_final_reminder' : 'proposal_upcoming_reminder';
                    if ($this->claim($request, $type . '_' . $daysLeft, $deadlineKey)) {
                        $this->notifyRequestor($request, $daysLeft === min($before) ? 'final_reminder' : 'upcoming_reminder', $type . ':' . $deadlineKey . ':' . $daysLeft);
                        $sent++;
                    }
                } elseif ($daysLeft === 0) {
                    if ($this->claim($request, 'proposal_deadline_today', $deadlineKey)) {
                        $this->notifyRequestor($request, 'deadline_today', 'deadline_today:' . $deadlineKey);
                        $sent++;
                    }
                } elseif ($daysLeft < 0) {
                    $late = -$daysLeft;
                    if ($late === 1 && $this->claim($request, 'proposal_overdue', $deadlineKey)) {
                        $request->addHistory('proposal_overdue', 'The activity proposal is overdue: no Final Copy was submitted by ' . $deadline->format('M j, Y') . '. The reservation is not affected; the Supply Office decides any action.');
                        $this->notifyRequestor($request, 'overdue', 'overdue:' . $deadlineKey);
                        $this->notifySupplyOffice($request, 'overdue', 'overdue:' . $deadlineKey);
                        $sent++;
                    } elseif ($late > 1 && ($late - 1) % $repeat === 0 && $this->claim($request, 'proposal_outstanding_' . $late, $deadlineKey)) {
                        $this->notifySupplyOffice($request, 'outstanding', 'outstanding:' . $deadlineKey . ':' . $late);
                        $sent++;
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Activity proposal reminder failed', ['request_id' => $request->id, 'error' => $e->getMessage()]);
            }
        }

        return $sent;
    }

    private function claim(FacilityRequest $request, string $type, string $scheduledFor): bool
    {
        $now = now()->toDateTimeString();

        return DB::table('reservation_reminder_logs')->insertOrIgnore([
            'facility_request_id' => $request->id,
            'reminder_type' => $type,
            'scheduled_for' => $scheduledFor . ' 00:00:00',
            'sent_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]) === 1;
    }

    // ── Notifications (short, once per event) ────────────────────────────────

    public function notifyRequestor(FacilityRequest $request, string $kind, string $key): void
    {
        if ($request->requester) {
            $this->sendOnce($request->requester, new ActivityProposalNotice($request, $kind, $key));
        }
    }

    public function notifySupplyOffice(FacilityRequest $request, string $kind, string $key): void
    {
        foreach (User::query()->withAnyRole(['admin', 'supply_office'])->get() as $user) {
            $this->sendOnce($user, new ActivityProposalNotice($request, $kind, $key, 'supply_office'));
        }
    }

    private function sendOnce(User $user, ActivityProposalNotice $notice): void
    {
        try {
            $exists = $user->notifications()->where('type', ActivityProposalNotice::class)->get()
                ->contains(fn ($n) => ($n->data['notice_key'] ?? null) === $notice->noticeKey);
            if (! $exists) {
                $user->notify($notice);
            }
        } catch (\Throwable $e) {
            Log::warning('Activity proposal notification failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }
}

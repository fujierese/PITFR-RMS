<?php

namespace App\Console\Commands;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\RequestStatusChanged;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Issue APR-14: reminders, escalation and expiry for requests that wait too long.
 *
 * - A stage waiting longer than `reminder_after_hours` reminds the people who must act.
 * - Longer than `escalate_after_hours` also alerts the Supply Office.
 * - A request whose reservation start passes without final approval is cancelled as expired.
 *
 * "Waiting since" is the last recorded action on the request, so each new stage
 * starts its own clock. Each reminder is sent once per stage (reservation_reminder_logs).
 */
class ProcessPendingReviewDeadlines extends Command
{
    protected $signature = 'facility-requests:review-deadlines {--dry-run : List what would happen without changing or sending anything}';

    protected $description = 'Remind, escalate and expire pending requests that are waiting too long for review.';

    public function handle(): int
    {
        $now = Carbon::now();
        $config = config('pitfr.review_sla');
        $dryRun = (bool) $this->option('dry-run');

        $requests = FacilityRequest::query()
            ->whereIn('status', ['pending', 'needs_reschedule'])
            ->with(['requester', 'reservationSchedule'])
            ->get();

        foreach ($requests as $request) {
            try {
                if (($config['expire_when_start_passes'] ?? true) && $this->startHasPassed($request, $now)) {
                    if ($dryRun) {
                        $this->line("EXPIRE    {$request->control_number} (start time has passed, status {$request->status})");
                        continue;
                    }
                    $this->expire($request);
                    continue;
                }

                if ($request->status !== 'pending' || $request->revisionHistories()->where('status', 'pending')->exists()) {
                    continue; // Waiting on the requestor or on a proposal with its own deadline.
                }

                $waitingSince = $this->waitingSince($request);
                $hoursWaiting = $waitingSince->diffInHours($now);

                if ($dryRun) {
                    [$stage, $users] = self::pendingStage($request);
                    $action = match (true) {
                        $hoursWaiting >= (int) $config['escalate_after_hours'] => 'ESCALATE',
                        $hoursWaiting >= (int) $config['reminder_after_hours'] => 'REMIND  ',
                        default => null,
                    };
                    if ($action) {
                        $this->line(sprintf('%s  %s (waiting %dh for %s; %d recipient(s))', $action, $request->control_number, $hoursWaiting, $stage, $users->count()));
                    }
                    continue;
                }

                if ($hoursWaiting >= (int) $config['reminder_after_hours']) {
                    $this->remind($request, $waitingSince, $now);
                }
                if ($hoursWaiting >= (int) $config['escalate_after_hours']) {
                    $this->escalate($request, $waitingSince, $now);
                }
            } catch (\Throwable $exception) {
                Log::error('Review deadline processing failed', ['request_id' => $request->id, 'error' => $exception->getMessage()]);
            }
        }

        return self::SUCCESS;
    }

    /**
     * The stage the request is waiting on and the users who must act on it.
     *
     * @return array{0: string, 1: Collection<int, User>}
     */
    public static function pendingStage(FacilityRequest $request): array
    {
        $supplyOffice = fn (): Collection => User::query()->withAnyRole(['supply_office', 'admin'])->get();

        // B: an outsider inquiry still with IGP is waiting on IGP, not on the custodian.
        if ($request->isAwaitingIgpReview()) {
            return ['IGP review', User::query()->withAnyRole(['igp'])->get()];
        }

        if ($request->venue_status !== 'approved') {
            $ids = Venue::query()->whereIn('name', $request->getVenueNames())->pluck('custodian_id')->filter();
            return ['venue confirmation', User::query()->whereIn('id', $ids)->get()];
        }

        if (in_array($request->supply_office_review_status, [null, '', 'pending'], true)) {
            return ['Supply Office availability review', $supplyOffice()];
        }

        if ($request->equipment_status !== 'approved') {
            $statuses = $request->equipment_custodian_statuses ?? [];
            $ids = collect($request->getAssignedEquipmentCustodianIds())
                ->reject(fn ($id): bool => ($statuses[(string) $id] ?? null) === 'approved');
            $users = User::query()->whereIn('id', $ids)->get();

            // Items with no custodian (APR-12) can only be resolved by the Supply Office.
            return ['equipment endorsement', $users->isEmpty() ? $supplyOffice() : $users];
        }

        if ($request->isOutsiderInquiry() && in_array($request->outsider_stage, ['submitted', 'igp_review', 'approved'], true)) {
            return ['IGP coordination', User::query()->withAnyRole(['igp'])->get()];
        }

        return ['Supply Office final approval', $supplyOffice()];
    }

    private function waitingSince(FacilityRequest $request): Carbon
    {
        $lastAction = $request->histories()->where('action', '!=', 'review_escalated')->max('occurred_at');

        return Carbon::parse($lastAction ?? $request->created_at);
    }

    private function startHasPassed(FacilityRequest $request, Carbon $now): bool
    {
        $start = $request->reservationSchedule?->start_datetime;
        if (! $start && $request->start_date) {
            $start = Carbon::parse($request->start_date->toDateString() . ' ' . ($request->start_time ?: '00:00'));
        }

        return $start !== null && Carbon::parse($start)->lessThanOrEqualTo($now);
    }

    private function remind(FacilityRequest $request, Carbon $waitingSince, Carbon $now): void
    {
        [$stage, $users] = self::pendingStage($request);
        if ($users->isEmpty() || ! $this->claim($request, 'review_reminder', $waitingSince, $now)) {
            return;
        }

        Notification::send($users, new RequestStatusChanged(
            $request,
            'review_reminder',
            "Request {$request->control_number} has been waiting for {$stage} since {$waitingSince->format('M j, Y g:i A')}. Please review it.",
        ));
    }

    private function escalate(FacilityRequest $request, Carbon $waitingSince, Carbon $now): void
    {
        [$stage] = self::pendingStage($request);
        $supplyOffice = User::query()->withAnyRole(['supply_office', 'admin'])->get();
        if ($supplyOffice->isEmpty() || ! $this->claim($request, 'review_escalation', $waitingSince, $now)) {
            return;
        }

        $request->addHistory('review_escalated', "Waiting for {$stage} since {$waitingSince->format('M j, Y g:i A')}; escalated to the Supply Office.");
        Notification::send($supplyOffice, new RequestStatusChanged(
            $request,
            'review_escalation',
            "Overdue: request {$request->control_number} has been waiting for {$stage} since {$waitingSince->format('M j, Y g:i A')}.",
        ));
    }

    private function expire(FacilityRequest $request): void
    {
        $expired = DB::transaction(function () use ($request): bool {
            $locked = FacilityRequest::query()->lockForUpdate()->find($request->id);
            if (! $locked || ! in_array($locked->status, ['pending', 'needs_reschedule'], true)) {
                return false;
            }

            // Return stock reserved when the equipment custodians approved.
            if ($locked->equipment_status === 'approved') {
                foreach ($locked->getEquipmentQuantities() as $itemName => $quantity) {
                    $equipment = Equipment::whereRaw('LOWER(name) = ?', [mb_strtolower($itemName)])->lockForUpdate()->first();
                    if ($equipment) {
                        $equipment->quantity_available = min($equipment->quantity, $equipment->quantity_available + (int) $quantity);
                        $equipment->save();
                    }
                }
            }

            $locked->revisionHistories()->where('status', 'pending')->update(['status' => 'expired', 'responded_at' => now()]);
            $locked->update([
                'status' => 'cancelled',
                'venue_status' => 'cancelled',
                'equipment_status' => 'cancelled',
                'cancellation_status' => 'confirmed',
                'cancellation_reviewed_at' => now(),
                'cancellation_notes' => 'Expired: the reservation start time passed before final approval.',
            ]);
            $locked->addHistory('cancelled', 'Expired automatically: the reservation start time passed before the request received final approval.');

            return true;
        });

        if (! $expired || ! $request->requester) {
            return;
        }

        DB::afterCommit(function () use ($request): void {
            $request->requester->notify(new RequestStatusChanged(
                $request->fresh(),
                'request_expired',
                'Your request expired because the reservation date passed before it received final approval. Submit a new request for another date.',
            ));
        });
    }

    private function claim(FacilityRequest $request, string $type, Carbon $waitingSince, Carbon $now): bool
    {
        return DB::table('reservation_reminder_logs')->insertOrIgnore([
            'facility_request_id' => $request->id,
            'reminder_type' => $type,
            'scheduled_for' => $waitingSince->toDateTimeString(),
            'sent_at' => $now->toDateTimeString(),
            'created_at' => $now->toDateTimeString(),
            'updated_at' => $now->toDateTimeString(),
        ]) === 1;
    }
}

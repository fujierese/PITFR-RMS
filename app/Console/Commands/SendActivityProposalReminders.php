<?php

namespace App\Console\Commands;

use App\Services\ActivityProposals;
use Illuminate\Console\Command;

/** Additional Part 2: Activity Proposal deadline reminders and overdue notices. */
class SendActivityProposalReminders extends Command
{
    protected $signature = 'activity-proposals:reminders';

    protected $description = 'Send Activity Proposal deadline reminders and overdue notices (once each).';

    public function handle(ActivityProposals $proposals): int
    {
        $this->info($proposals->sendReminders() . ' reminder(s) sent.');

        return self::SUCCESS;
    }
}

<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;

/**
 * QA-2: records every enable, disable and re-enable of a venue or equipment item in
 * the audit history (who, what, when), whichever screen made the change.
 */
final class ResourceAudit
{
    public static function statusChanged(string $kind, string $name, bool $enabled): void
    {
        $actor = auth()->user();
        $label = $kind === 'venue' ? 'Venue' : 'Equipment';

        try {
            AuditLog::create([
                'actor_id' => $actor?->id,
                'target_user_id' => null,
                'action' => $kind . ($enabled ? '_enabled' : '_disabled'),
                'details' => "{$label} \"{$name}\" was " . ($enabled ? 're-enabled' : 'disabled') . ($actor ? " by {$actor->name}." : '.'),
                'old_values' => ['status' => $enabled ? 'Disabled' : 'Enabled'],
                'new_values' => ['status' => $enabled ? 'Enabled' : 'Disabled'],
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Could not record the ' . $kind . ' status change in the audit history: ' . $exception->getMessage());
        }
    }
}

<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Append-only audit trail of request actions (issue APR-03).
 * Entries can be created but never edited or deleted through the application.
 */
class RequestHistory extends Model
{
    use \App\Models\Concerns\SerializesDatesInAppTimezone;

    protected $fillable = [
        'facility_request_id', 'user_id', 'actor_role', 'request_version', 'request_fingerprint',
        'action', 'detail', 'occurred_at',
    ];

    protected $casts = [
        'occurred_at'     => 'datetime',
        'request_version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Request history entries cannot be changed.');
        });

        static::deleting(function (): void {
            throw new LogicException('Request history entries cannot be deleted.');
        });
    }

    public function facilityRequest()
    {
        return $this->belongsTo(FacilityRequest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function actorRoleLabel(): ?string
    {
        if (! $this->actor_role) {
            return null;
        }

        return match (true) {
            $this->actor_role === 'supply_office'           => 'Supply Office',
            $this->actor_role === 'igp'                     => 'IGP',
            $this->actor_role === 'system'                  => 'System',
            str_starts_with($this->actor_role, 'custodian') => 'Custodian',
            default                                         => ucwords(str_replace('_', ' ', $this->actor_role)),
        };
    }
}

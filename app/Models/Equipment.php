<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Equipment extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'custodian_id', 'quantity', 'quantity_available'];

    public function custodian()
    {
        return $this->belongsTo(User::class, 'custodian_id');
    }

    public function requestEquipment()
    {
        return $this->hasMany(RequestEquipment::class);
    }

    public function isAvailable(int $requested): bool
    {
        return $this->quantity_available >= $requested;
    }

    public function availabilityBadgeClass(): string
    {
        if ($this->quantity_available <= 0) {
            return 'bg-red-100 text-red-700';
        }

        if ($this->quantity_available == $this->quantity) {
            return 'bg-green-100 text-green-700';
        }

        return 'bg-yellow-100 text-yellow-700';
    }

    public function reserve(int $quantity): void
    {
        $this->decrement('quantity_available', $quantity);
    }

    public function release(int $quantity): void
    {
        $this->increment('quantity_available', $quantity);
    }
}
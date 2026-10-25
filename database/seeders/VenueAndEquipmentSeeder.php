<?php
namespace Database\Seeders;

use App\Models\Equipment;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;

class VenueAndEquipmentSeeder extends Seeder
{
    public function run(): void
    {
        $custodians = User::query()->whereIn('username', [
            'asala@gmail.com',
            'ctado@gmail.com',
            'mmercado@gmail.com',
            'rguillemer@gmail.com',
            'lalmerino@gmail.com',
            'jrvillas@gmail.com',
            'jsuralta@gmail.com',
        ])->pluck('id', 'username');

        $this->upsertVenue('Conference Hall & Interaction Center (CHIC)', 200, $custodians['asala@gmail.com']);
        $this->upsertVenue('Gymnasium', 1000, $custodians['ctado@gmail.com']);
        $this->upsertVenue('Balay Alumni', 50, $custodians['mmercado@gmail.com']);
        Venue::query()->where('name', 'Balay Alumni Hall')->delete();
        $this->upsertVenue('Oval Grounds', null, $custodians['ctado@gmail.com']);
        $this->upsertVenue('Covered Court', null, $custodians['ctado@gmail.com']);
        $this->upsertVenue('Volleyball Court', null, $custodians['ctado@gmail.com']);

        $fanAlternate = [$custodians['jrvillas@gmail.com']];
        $soundSystem = Equipment::query()->where('name', 'Sound System')->first();
        $soundSystemOutstanding = $soundSystem
            ? max(0, (int) $soundSystem->quantity - (int) $soundSystem->quantity_available)
            : 0;
        $this->upsertEquipment(
            'Sound System',
            3,
            $custodians['rguillemer@gmail.com'],
            [],
            [],
            true,
            max(0, 3 - $soundSystemOutstanding),
        );
        $this->upsertEquipment('Wireless Microphones', 3, $custodians['rguillemer@gmail.com'], [], ['Wireless Microphone', 'Wireless Microphones']);
        $this->upsertEquipment('Non-Wireless Microphones', 3, $custodians['rguillemer@gmail.com'], [], ['Non-wireless Microphone', 'Non-Wireless Microphones']);
        $this->upsertEquipment('Aircon', 1, $custodians['lalmerino@gmail.com'], [], [], false, null, true, true);
        $this->upsertEquipment('Lights', 1, $custodians['rguillemer@gmail.com']);
        $this->upsertEquipment('Podium', 1, $custodians['asala@gmail.com']);
        $this->upsertEquipment('HDMI', 1, $custodians['asala@gmail.com']);
        $this->upsertEquipment('Television (CHIC)', 1, $custodians['asala@gmail.com']);
        $this->upsertEquipment('Television (Balay Alumni)', 1, $custodians['mmercado@gmail.com'], [], [], true, 0, false);
        $this->upsertEquipment('Canopies', 10, $custodians['jsuralta@gmail.com']);
        $this->upsertEquipment('Industrial Fans', 6, $custodians['lalmerino@gmail.com'], $fanAlternate);
        $this->upsertEquipment('Iwata Cooler Fans', 4, $custodians['lalmerino@gmail.com'], $fanAlternate);
        $this->upsertEquipment('Tables', 10, $custodians['jsuralta@gmail.com']);
        $this->upsertEquipment('Chairs', 50, $custodians['mmercado@gmail.com'], [], ['Chair', 'Chairs'], false, null, true, true, true);
        $this->upsertEquipment('Monobloc Chairs', 600, $custodians['jsuralta@gmail.com'], [], ['Monobloc chair', 'Monobloc chairs']);
    }

    private function upsertVenue(string $name, ?int $capacity, int $custodianId, array $aliases = []): void
    {
        $canonicalName = $name;
        $venue = Venue::query()->where('name', $canonicalName)->first()
            ?? Venue::query()->whereIn('name', array_merge([$canonicalName], $aliases))->first();

        if (! $venue) {
            $venue = new Venue([
                'name' => $canonicalName,
                'custodian_id' => $custodianId,
            ]);
        }

        if ($venue->exists) {
            if ($venue->custodian_id === null) {
                $venue->custodian_id = $custodianId;
            }
            if ($capacity !== null && $venue->capacity === null) {
                $venue->capacity = $capacity;
            }
            if (! $venue->is_active) {
                $venue->is_active = true;
            }
            $venue->save();
            return;
        }

        $venue->fill([
            'name' => $canonicalName,
            'custodian_id' => $custodianId,
        ]);
        if ($capacity !== null) {
            $venue->capacity = $capacity;
        }
        $venue->is_active = true;
        $venue->save();
    }

    private function upsertEquipment(
        string $name,
        int $quantity,
        int $custodianId,
        array $alternateIds = [],
        array $aliases = [],
        bool $forceSeedQuantity = false,
        ?int $available = null,
        bool $isActive = true,
        bool $forceCustodian = false,
        bool $forceTotalQuantity = false,
    ): void
    {
        $canonicalName = match (mb_strtolower(trim($name))) {
            'wireless microphone' => 'Wireless Microphones',
            'wireless microphones' => 'Wireless Microphones',
            'non-wireless microphone' => 'Non-Wireless Microphones',
            'non-wireless microphones' => 'Non-Wireless Microphones',
            'non wireless microphone' => 'Non-Wireless Microphones',
            'non wireless microphones' => 'Non-Wireless Microphones',
            default => $name,
        };

        $equipment = Equipment::query()->where('name', $canonicalName)->first()
            ?? Equipment::query()->whereIn('name', array_merge([$canonicalName], $aliases))->first();

        if (! $equipment) {
            $equipment = new Equipment(['name' => $canonicalName]);
        }

        if ($equipment->exists) {
            if ($forceCustodian || $equipment->custodian_id === null) {
                $equipment->custodian_id = $custodianId;
            }
            if ($forceSeedQuantity || $forceTotalQuantity || $equipment->quantity === null) {
                $equipment->quantity = $quantity;
            }
            if ($forceSeedQuantity || $equipment->quantity_available === null) {
                $equipment->quantity_available = $available ?? $quantity;
            } elseif ($forceTotalQuantity) {
                $equipment->quantity_available = min((int) $equipment->quantity_available, $quantity);
            }
            if (empty($equipment->authorized_custodian_ids) && ! empty($alternateIds)) {
                $equipment->authorized_custodian_ids = $alternateIds;
            }
            $equipment->is_active = $isActive;
            $equipment->save();
            return;
        }

        $equipment->fill([
            'name' => $canonicalName,
            'quantity' => $quantity,
            'quantity_available' => $available ?? $quantity,
            'custodian_id' => $custodianId,
            'authorized_custodian_ids' => $alternateIds,
            'is_active' => $isActive,
        ]);
        $equipment->save();
    }
}
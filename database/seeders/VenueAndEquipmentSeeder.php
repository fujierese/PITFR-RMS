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

        $this->upsertVenue('Conference Hall & Interaction Center (CHIC)', null, $custodians['asala@gmail.com']);
        $this->upsertVenue('Gymnasium', 1000, $custodians['ctado@gmail.com']);
        $this->upsertVenue('Balay Alumni', 50, $custodians['mmercado@gmail.com']);
        Venue::query()->where('name', 'Balay Alumni Hall')->delete();
        $this->upsertVenue('Oval Grounds', null, $custodians['ctado@gmail.com']);
        $this->upsertVenue('Covered Court', null, $custodians['ctado@gmail.com']);
        $this->upsertVenue('Volleyball Court', null, $custodians['ctado@gmail.com']);

        $fanAlternate = [$custodians['jrvillas@gmail.com']];
        $this->upsertEquipment('Sound System', 1, $custodians['rguillemer@gmail.com']);
        $this->upsertEquipment('Wireless Microphones', 1, $custodians['rguillemer@gmail.com'], [], ['Wireless Microphone', 'Wireless Microphones']);
        $this->upsertEquipment('Non-Wireless Microphones', 1, $custodians['rguillemer@gmail.com'], [], ['Non-wireless Microphone', 'Non-Wireless Microphones']);
        $this->upsertEquipment('Canopies', 10, $custodians['jsuralta@gmail.com']);
        $this->upsertEquipment('Industrial Fans', 6, $custodians['lalmerino@gmail.com'], $fanAlternate);
        $this->upsertEquipment('Iwata Cooler Fans', 4, $custodians['lalmerino@gmail.com'], $fanAlternate);
        $this->upsertEquipment('Tables', 10, $custodians['jsuralta@gmail.com']);
        $this->upsertEquipment('Monobloc Chairs', 600, $custodians['jsuralta@gmail.com'], [], ['Chairs', 'Monobloc chairs']);
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

    private function upsertEquipment(string $name, int $quantity, int $custodianId, array $alternateIds = [], array $aliases = []): void
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

        $equipment->fill([
            'name' => $canonicalName,
            'quantity' => $quantity,
            'quantity_available' => $quantity,
            'custodian_id' => $custodianId,
            'authorized_custodian_ids' => $alternateIds,
            'is_active' => true,
        ]);
        $equipment->save();
    }
}
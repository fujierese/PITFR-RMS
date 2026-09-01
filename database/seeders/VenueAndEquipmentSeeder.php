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
        $this->upsertVenue('Balay Alumni Hall', 50, $custodians['mmercado@gmail.com'], ['Balay Alumni']);
        $this->upsertVenue('Oval Grounds', null, $custodians['ctado@gmail.com']);
        $this->upsertVenue('Covered Court', null, $custodians['ctado@gmail.com']);
        $this->upsertVenue('Volleyball Court', null, $custodians['ctado@gmail.com']);

        $fanAlternate = [$custodians['jrvillas@gmail.com']];
        $this->upsertEquipment('Sound System', 1, $custodians['rguillemer@gmail.com']);
        $this->upsertEquipment('Wireless Microphones', 1, $custodians['rguillemer@gmail.com'], [], ['Wireless Microphone']);
        $this->upsertEquipment('Non-Wireless Microphones', 1, $custodians['rguillemer@gmail.com'], [], ['Non-wireless Microphone']);
        $this->upsertEquipment('Canopies', 10, $custodians['jsuralta@gmail.com']);
        $this->upsertEquipment('Industrial Fans', 6, $custodians['lalmerino@gmail.com'], $fanAlternate);
        $this->upsertEquipment('Iwata Cooler Fans', 4, $custodians['lalmerino@gmail.com'], $fanAlternate);
        $this->upsertEquipment('Tables', 10, $custodians['jsuralta@gmail.com']);
        $this->upsertEquipment('Monobloc Chairs', 600, $custodians['jsuralta@gmail.com'], [], ['Chairs', 'Monobloc chairs']);
    }

    private function upsertVenue(string $name, ?int $capacity, int $custodianId, array $aliases = []): void
    {
        $venue = Venue::query()->where('name', $name)->first()
            ?? Venue::query()->whereIn('name', $aliases)->first();
        if (! $venue) {
            $venue = new Venue([
                'name' => $name,
                'custodian_id' => $custodianId,
            ]);
        } elseif ($venue->name !== $name && $venue->requestVenues()->exists()) {
            $name = $venue->name;
        }

        $venue->fill([
            'name' => $name,
        ]);
        if ($capacity !== null) {
            $venue->capacity = $capacity;
        }
        $venue->is_active = true;
        $venue->save();
    }

    private function upsertEquipment(string $name, int $quantity, int $custodianId, array $alternateIds = [], array $aliases = []): void
    {
        $equipment = Equipment::query()->where('name', $name)->first()
            ?? Equipment::query()->whereIn('name', $aliases)->first();
        if (! $equipment) {
            $equipment = new Equipment(['name' => $name]);
        } elseif ($equipment->name !== $name && $equipment->requestEquipment()->exists()) {
            $name = $equipment->name;
        }

        $equipment->fill([
            'name' => $name,
            'quantity' => $quantity,
            'quantity_available' => $quantity,
            'custodian_id' => $custodianId,
            'authorized_custodian_ids' => $alternateIds,
            'is_active' => true,
        ]);
        $equipment->save();
    }
}
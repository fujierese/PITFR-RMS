<?php

namespace Database\Factories;

use App\Models\FacilityRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FacilityRequest>
 */
class FacilityRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'control_number' => 'REQ-' . $this->faker->year() . '-' . $this->faker->numberBetween(1000, 9999),
            'date_requested' => Carbon::now(),
            'department' => $this->faker->word(),
            'name_of_activity' => $this->faker->sentence(),
            'expected_participants' => $this->faker->numberBetween(10, 100),
            'start_date' => Carbon::now()->addDays(7),
            'end_date' => Carbon::now()->addDays(7),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'venue' => [$this->faker->word()],
            'equipment' => [$this->faker->word()],
            'equipment_quantities' => [1],
            'other_venue' => null,
            'requested_by_id' => User::factory(),
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
            'priority' => 'regular',
            'is_emergency' => false,
        ];
    }

    /**
     * State for approved requests
     */
    public function approved(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'approved',
                'venue_status' => 'approved',
                'equipment_status' => 'approved',
                'approved_by' => 'admin_test',
                'approved_by_id' => User::where('role', 'admin')->first()?->id ?? 1,
                'approved_date' => Carbon::now(),
            ];
        });
    }

    /**
     * State for rejected requests
     */
    public function rejected(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'rejected',
                'approved_by' => 'admin_test',
                'approved_by_id' => User::where('role', 'admin')->first()?->id ?? 1,
                'approved_date' => Carbon::now(),
                'notes' => 'Request rejected',
            ];
        });
    }

    /**
     * State for needs_reschedule requests
     */
    public function needsReschedule(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'needs_reschedule',
            ];
        });
    }

    /**
     * State for requests ready for final approval
     */
    public function readyForApproval(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'pending',
                'venue_status' => 'approved',
                'equipment_status' => 'approved',
            ];
        });
    }
}

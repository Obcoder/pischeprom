<?php

namespace Database\Factories;

use App\Enums\Logistics\TripStatus;
use App\Models\LogisticsTrip;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LogisticsTrip> */
class LogisticsTripFactory extends Factory
{
    protected $model = LogisticsTrip::class;

    public function definition(): array
    {
        $departure = now()->addDays($this->faker->numberBetween(1, 30))->startOfHour();

        return [
            'status' => TripStatus::Draft,
            'planned_departure_at' => $departure,
            'planned_arrival_at' => $departure->copy()->addDay(),
            'cargo_description' => $this->faker->sentence(4),
            'cargo_weight_kg' => $this->faker->numberBetween(1000, 18000),
            'routing_profile' => 'truck',
        ];
    }
}

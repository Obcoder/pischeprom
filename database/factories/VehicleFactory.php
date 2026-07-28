<?php

namespace Database\Factories;

use App\Enums\Logistics\VehicleStatus;
use App\Enums\Logistics\VehicleType;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Vehicle> */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        return [
            'name' => 'Грузовик '.$this->faker->unique()->numberBetween(100, 999),
            'registration_number' => 'A'.$this->faker->unique()->numerify('###AA##'),
            'make' => $this->faker->randomElement(['КАМАЗ', 'Volvo', 'Scania', 'MAN']),
            'model' => $this->faker->bothify('##??'),
            'year' => $this->faker->numberBetween(2010, (int) date('Y')),
            'vehicle_type' => VehicleType::Truck,
            'status' => VehicleStatus::Active,
            'payload_capacity_kg' => 20000,
            'cargo_volume_m3' => 82,
            'curb_weight_kg' => 12000,
            'gross_weight_kg' => 32000,
            'length_m' => 16.5,
            'width_m' => 2.55,
            'height_m' => 4,
            'axle_count' => 5,
            'max_axle_load_t' => 10,
            'fuel_type' => 'diesel',
            'fuel_tank_capacity_l' => 600,
            'average_fuel_consumption_l_per_100km' => 32,
            'is_active' => true,
        ];
    }
}

<?php

namespace Database\Seeders;

use App\Models\ServiceArea;
use App\Models\User;
use App\Services\ServiceAreaService;
use Illuminate\Database\Seeder;

/**
 * Demo service areas and addresses for local development only, not client data.
 */
class ServiceAreaSeeder extends Seeder
{
    public function run(ServiceAreaService $areas): void
    {
        $postcodes = ServiceArea::factory()->create(['name' => 'Central Mumbai (demo)', 'country' => 'IN']);
        $areas->syncPostcodes($postcodes, ['400001', '400002', '400020', '400021']);

        ServiceArea::factory()->radius(18.5204, 73.8567, 10_000)->create(['name' => 'Pune 10 km (demo)']);

        ServiceArea::factory()->polygon([
            [12.99, 77.55], [13.01, 77.65], [12.93, 77.68], [12.90, 77.58],
        ])->create(['name' => 'Central Bengaluru zone (demo)']);

        $customer = User::where('email', 'test@example.com')->firstOrFail();
        $customer->addresses()->createMany([
            ['label' => 'Home', 'line1' => '12 Marine Drive', 'city' => 'Mumbai', 'region' => 'Maharashtra', 'postcode' => '400 020', 'country' => 'IN'],
            ['label' => 'Parents', 'line1' => '4 FC Road', 'city' => 'Pune', 'region' => 'Maharashtra', 'postcode' => '411004', 'country' => 'IN', 'latitude' => 18.5236, 'longitude' => 73.8478],
            ['label' => 'Office', 'line1' => '1 Tech Park', 'city' => 'Hyderabad', 'region' => 'Telangana', 'postcode' => '500081', 'country' => 'IN'],
        ]);
    }
}

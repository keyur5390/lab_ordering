<?php

namespace Database\Seeders;

use App\Models\CollectionClosure;
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

        // Demo schedule for every area: Monday–Saturday mornings and evenings, 30-minute slots.
        foreach (ServiceArea::all() as $area) {
            $area->update(['slot_minutes' => 30, 'capacity_per_slot' => 2, 'lead_time_minutes' => 120, 'booking_window_days' => 7]);
            foreach (range(1, 6) as $weekday) {
                $area->hours()->createMany([
                    ['weekday' => $weekday, 'opens_at' => '07:00', 'closes_at' => '11:00'],
                    ['weekday' => $weekday, 'opens_at' => '17:00', 'closes_at' => '19:00'],
                ]);
            }
        }
        CollectionClosure::create([
            'starts_on' => now()->addDays(3)->toDateString(),
            'ends_on' => now()->addDays(3)->toDateString(),
            'reason' => 'Demo holiday',
        ]);
        CollectionClosure::create([
            'service_area_id' => $postcodes->id,
            'starts_on' => now()->addDays(1)->toDateString(),
            'ends_on' => now()->addDays(1)->toDateString(),
            'starts_at' => '08:00',
            'ends_at' => '09:00',
            'reason' => 'Demo staff training',
        ]);

        $customer = User::where('email', 'test@example.com')->firstOrFail();
        $customer->addresses()->createMany([
            ['label' => 'Home', 'line1' => '12 Marine Drive', 'city' => 'Mumbai', 'region' => 'Maharashtra', 'postcode' => '400 020', 'country' => 'IN'],
            ['label' => 'Parents', 'line1' => '4 FC Road', 'city' => 'Pune', 'region' => 'Maharashtra', 'postcode' => '411004', 'country' => 'IN', 'latitude' => 18.5236, 'longitude' => 73.8478],
            ['label' => 'Office', 'line1' => '1 Tech Park', 'city' => 'Hyderabad', 'region' => 'Telangana', 'postcode' => '500081', 'country' => 'IN'],
        ]);
    }
}

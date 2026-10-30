<?php

namespace Database\Seeders;

use App\Actions\CreateSelfPatient;
use App\Enums\Currency;
use App\Enums\DiscountType;
use App\Enums\PatientRelationship;
use App\Enums\Sex;
use App\Enums\UserRole;
use App\Models\Coupon;
use App\Models\ServiceArea;
use App\Models\User;
use App\Settings\Settings;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $customer = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $admin = User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        User::factory()->create([
            'name' => 'Lab User',
            'email' => 'lab@example.com',
            'role' => UserRole::LabStaff,
        ]);

        // Model events are off while seeding, so create the "Self" profiles explicitly.
        $createSelfPatient = app(CreateSelfPatient::class);
        $createSelfPatient->handle($admin);
        $createSelfPatient->handle($customer)->update(['date_of_birth' => '1988-04-12', 'sex' => Sex::Female]);
        $customer->patients()->create([
            'name' => 'Ravi User',
            'relationship' => PatientRelationship::Parent,
            'date_of_birth' => '1956-09-30',
            'sex' => Sex::Male,
        ]);

        // Demo only: on a real install the admin sets these in Admin → Settings.
        app(Settings::class)->setCurrency(Currency::INR);
        app(Settings::class)->setTimezone('Asia/Kolkata');
        app(Settings::class)->setCancellationCutoffHours(12);

        $this->call([CatalogueSeeder::class, ServiceAreaSeeder::class]);

        // Demo pricing: ₹49 collection in Pune, free from ₹999; a 10% welcome code.
        ServiceArea::where('name', 'like', 'Pune%')->update(['collection_fee_amount' => 4900]);
        app(Settings::class)->setFreeCollectionAbove(99900);
        Coupon::create([
            'code' => 'WELCOME10',
            'description' => '10% off your first order',
            'discount_type' => DiscountType::Percent,
            'discount_value' => 10,
            'max_discount_amount' => 50000,
            'per_customer_limit' => 1,
        ]);

        // Demo collector covering every demo area.
        User::factory()->create([
            'name' => 'Ravi Collector',
            'email' => 'collector@example.com',
            'role' => UserRole::Collector,
        ])->collectorAreas()->attach(ServiceArea::pluck('id'));
    }
}

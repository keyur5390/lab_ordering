<?php

namespace Database\Seeders;

use App\Actions\CreateSelfPatient;
use App\Enums\Currency;
use App\Enums\PatientRelationship;
use App\Enums\Sex;
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

        // Demo only: on a real install the admin chooses the currency and timezone in Admin → Settings.
        app(Settings::class)->setCurrency(Currency::INR);
        app(Settings::class)->setTimezone('Asia/Kolkata');

        $this->call([CatalogueSeeder::class, ServiceAreaSeeder::class]);
    }
}

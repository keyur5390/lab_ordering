<?php

namespace Database\Seeders;

use App\Enums\Currency;
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

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        // Demo only: on a real install the admin chooses the currency in Admin → Settings.
        app(Settings::class)->setCurrency(Currency::INR);

        $this->call(CatalogueSeeder::class);
    }
}

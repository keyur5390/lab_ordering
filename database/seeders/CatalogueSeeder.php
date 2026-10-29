<?php

namespace Database\Seeders;

use App\Enums\DiscountType;
use App\Models\LabPackage;
use App\Models\LabTest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo catalogue for local development only. Names, prices and turnaround times are
 * placeholders, not client data: the real catalogue is entered by admins.
 */
class CatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $tests = collect([
            ['Complete Blood Count', 'CBC', 'Blood', null, 24, 40000],
            ['Lipid Profile', 'LIPID', 'Blood', 'Fast for 10–12 hours before the sample is taken. Water is fine.', 24, 60000],
            ['Fasting Blood Sugar', 'FBS', 'Blood', 'Fast for 8–10 hours before the sample is taken.', 12, 15000],
            ['HbA1c', 'HBA1C', 'Blood', null, 24, 50000],
            ['Thyroid Profile (T3, T4, TSH)', 'THY', 'Blood', null, 48, 70000],
            ['Liver Function Test', 'LFT', 'Blood', null, 24, 65000],
            ['Kidney Function Test', 'KFT', 'Blood', null, 24, 65000],
            ['Urine Routine', 'URINE', 'Urine', 'Collect the first urine of the morning in the sterile container.', 12, 20000],
            ['Vitamin D (25-OH)', 'VITD', 'Blood', null, 72, 120000],
        ])->mapWithKeys(fn (array $row) => [$row[1] => LabTest::create([
            'name' => $row[0],
            'code' => $row[1],
            'slug' => Str::slug($row[0]),
            'description' => "Demo description for {$row[0]}.",
            'sample_type' => $row[2],
            'preparation' => $row[3],
            'turnaround_hours' => $row[4],
            'price_amount' => $row[5],
            'is_active' => true,
        ])]);

        // Demo: one test needs a prescription.
        $tests['VITD']->update(['requires_prescription' => true]);

        LabPackage::create([
            'name' => 'Basic Health Check',
            'code' => 'BASIC',
            'slug' => 'basic-health-check',
            'description' => 'Demo package with a fixed price.',
            'pricing_mode' => 'fixed',
            'price_amount' => 99900,
            'is_active' => true,
        ])->tests()->sync($tests->only(['CBC', 'FBS', 'URINE'])->pluck('id'));

        LabPackage::factory()->discounted(DiscountType::Percent, 20)->create([
            'name' => 'Full Body Checkup',
            'code' => 'FULL',
            'slug' => 'full-body-checkup',
            'description' => 'Demo package priced at 20% off its tests.',
        ])->tests()->sync($tests->only(['CBC', 'LIPID', 'FBS', 'HBA1C', 'THY', 'LFT', 'KFT'])->pluck('id'));
    }
}

<?php

namespace Database\Factories;

use App\Enums\PatientRelationship;
use App\Enums\Sex;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    /**
     * Define the model's default state: a complete family member.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->name(),
            'relationship' => fake()->randomElement([PatientRelationship::Spouse, PatientRelationship::Child, PatientRelationship::Parent]),
            'date_of_birth' => fake()->dateTimeBetween('-80 years', '-1 year')->format('Y-m-d'),
            'sex' => fake()->randomElement(Sex::cases()),
            'phone' => null,
            'email' => null,
        ];
    }

    public function self(): static
    {
        return $this->state(fn (array $attributes) => ['relationship' => PatientRelationship::Self]);
    }

    /** Missing date of birth and sex, like a "Self" profile right after sign-up. */
    public function incomplete(): static
    {
        return $this->state(fn (array $attributes) => ['date_of_birth' => null, 'sex' => null]);
    }
}

<?php

namespace App\Actions;

use App\Enums\PatientRelationship;
use App\Models\Patient;
use App\Models\User;

/**
 * Gives an account its "Self" patient profile, named after the account holder.
 * Date of birth and sex are filled in by the customer before ordering.
 */
class CreateSelfPatient
{
    public function handle(User $user): Patient
    {
        return $user->patients()->firstOrCreate(
            ['relationship' => PatientRelationship::Self],
            ['name' => $user->name, 'email' => $user->email],
        );
    }
}

<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;

/**
 * Customers manage only their own patient profiles. The "Self" profile can be edited but not deleted.
 */
class PatientPolicy
{
    public function update(User $user, Patient $patient): bool
    {
        return $patient->user_id === $user->id;
    }

    /** TODO(client): confirm the "Self" profile can't be deleted. */
    public function delete(User $user, Patient $patient): bool
    {
        return $patient->user_id === $user->id && ! $patient->isSelf();
    }
}

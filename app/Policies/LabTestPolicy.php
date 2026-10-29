<?php

namespace App\Policies;

use App\Models\LabTest;
use App\Models\User;

/**
 * Only admins manage the catalogue. Customers and guests see published items through the public catalogue.
 */
class LabTestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, LabTest $model): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, LabTest $model): bool
    {
        return $user->isAdmin();
    }
}

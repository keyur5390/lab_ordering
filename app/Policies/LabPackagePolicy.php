<?php

namespace App\Policies;

use App\Models\LabPackage;
use App\Models\User;

/**
 * Only admins manage the catalogue. Customers and guests see published items through the public catalogue.
 */
class LabPackagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, LabPackage $model): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, LabPackage $model): bool
    {
        return $user->isAdmin();
    }
}

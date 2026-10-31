<?php

namespace App\Policies;

use App\Models\ServiceArea;
use App\Models\User;

/** Only admins manage service areas. */
class ServiceAreaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, ServiceArea $area): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, ServiceArea $area): bool
    {
        return $user->isAdmin();
    }
}

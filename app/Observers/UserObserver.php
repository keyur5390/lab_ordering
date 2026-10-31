<?php

namespace App\Observers;

use App\Actions\CreateSelfPatient;
use App\Models\User;

class UserObserver
{
    public function __construct(private CreateSelfPatient $createSelfPatient) {}

    /** Every account has a "Self" patient profile, however the account was created. */
    public function created(User $user): void
    {
        $this->createSelfPatient->handle($user);
    }
}

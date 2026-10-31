<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\StaffInvitation;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Admin user management (client, 2026-10-31): change roles, disable or re-enable accounts, and invite
 * staff. Admins can't change or disable their own account, so the site always keeps an admin.
 */
class UserManagementService
{
    /**
     * @throws ValidationException
     */
    public function changeRole(User $actor, User $user, UserRole $role): void
    {
        $this->notSelf($actor, $user, 'role', 'You can\'t change your own role.');

        $user->role = $role;
        $user->save();
    }

    /**
     * @throws ValidationException
     */
    public function disable(User $actor, User $user): void
    {
        $this->notSelf($actor, $user, 'account', 'You can\'t disable your own account.');

        $user->forceFill(['disabled_at' => now()])->save();
    }

    public function enable(User $user): void
    {
        $user->forceFill(['disabled_at' => null])->save();
    }

    /**
     * Creates a staff account and emails a link to set the password. The link proves the address,
     * so the account counts as verified.
     */
    public function invite(string $name, string $email, UserRole $role): User
    {
        $user = new User(['name' => $name, 'email' => Str::lower($email), 'password' => Str::random(40)]);
        $user->role = $role;
        $user->email_verified_at = now();
        $user->save();

        $user->notify(new StaffInvitation(Password::broker()->createToken($user)));

        return $user;
    }

    /**
     * @throws ValidationException
     */
    private function notSelf(User $actor, User $user, string $field, string $message): void
    {
        if ($actor->is($user)) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}

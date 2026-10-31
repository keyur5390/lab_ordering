<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * The only way to grant a role until an admin user-management screen exists.
 * TODO(client): confirm who may create admins and whether a UI is needed.
 */
class SetUserRole extends Command
{
    /**
     * @var string
     */
    protected $signature = 'user:role {email : Email of an existing user} {role : One of the UserRole values}';

    /**
     * @var string
     */
    protected $description = "Set an existing user's role";

    public function handle(): int
    {
        $role = UserRole::tryFrom((string) $this->argument('role'));

        if ($role === null) {
            $this->error('Unknown role. Use one of: '.implode(', ', array_column(UserRole::cases(), 'value')).'.');

            return self::FAILURE;
        }

        $user = User::where('email', strtolower((string) $this->argument('email')))->first();

        if ($user === null) {
            $this->error('No user with that email.');

            return self::FAILURE;
        }

        $user->role = $role;
        $user->save();

        $this->info("{$user->email} is now {$role->label()}.");

        return self::SUCCESS;
    }
}

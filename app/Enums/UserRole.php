<?php

namespace App\Enums;

/**
 * Roles from PROJECT.md §2.
 *
 * TODO(client): phlebotomist/collector and lab staff roles are OPEN; add them here once confirmed.
 */
enum UserRole: string
{
    case Customer = 'customer';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::Admin => 'Admin',
        };
    }
}

<?php

namespace App\Enums;

/**
 * Roles from PROJECT.md §2.
 *
 * Lab staff (client, 2026-10-29): mark samples collected and received, upload reports, and read
 * all orders. TODO(client): the phlebotomist/collector role is OPEN.
 */
enum UserRole: string
{
    case Customer = 'customer';
    case Admin = 'admin';
    case LabStaff = 'lab_staff';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::Admin => 'Admin',
            self::LabStaff => 'Lab staff',
        };
    }
}

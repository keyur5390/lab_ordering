<?php

namespace App\Enums;

/**
 * Roles from PROJECT.md §2.
 *
 * Lab staff (client, 2026-10-29): mark samples collected and received, upload reports, and read
 * all orders. Collectors (client, 2026-10-30): home visits in their service areas.
 */
enum UserRole: string
{
    case Customer = 'customer';
    case Admin = 'admin';
    case LabStaff = 'lab_staff';
    case Collector = 'collector';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::Admin => 'Admin',
            self::LabStaff => 'Lab staff',
            self::Collector => 'Collector',
        };
    }
}

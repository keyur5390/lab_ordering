<?php

namespace App\Enums;

/**
 * How a patient profile relates to the account holder.
 * TODO(client): confirm the options.
 */
enum PatientRelationship: string
{
    /** The account holder. Each account has exactly one, created at sign-up. */
    case Self = 'self';
    case Spouse = 'spouse';
    case Child = 'child';
    case Parent = 'parent';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Self => 'Self',
            self::Spouse => 'Spouse',
            self::Child => 'Child',
            self::Parent => 'Parent',
            self::Other => 'Other',
        };
    }
}

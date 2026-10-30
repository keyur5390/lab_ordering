<?php

namespace App\Enums;

/**
 * Why a home visit failed (client, 2026-10-30). TODO(client): confirm the reasons.
 */
enum VisitFailureReason: string
{
    case NotHome = 'not_home';
    case Refused = 'refused';
    case WrongAddress = 'wrong_address';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NotHome => 'Nobody at home',
            self::Refused => 'Patient refused or wasn\'t ready',
            self::WrongAddress => 'Address not found',
            self::Other => 'Other',
        };
    }
}

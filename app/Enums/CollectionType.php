<?php

namespace App\Enums;

/**
 * Where the sample is taken (PROJECT.md §5): at the customer's address, or at a lab branch
 * (client, 2026-10-30).
 */
enum CollectionType: string
{
    case Home = 'home';
    case LabVisit = 'lab_visit';

    public function label(): string
    {
        return match ($this) {
            self::Home => 'Home collection',
            self::LabVisit => 'Lab visit',
        };
    }
}

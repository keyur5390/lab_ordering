<?php

namespace App\Enums;

/**
 * Patient sex, used by labs for reference ranges.
 * TODO(client): confirm the options.
 */
enum Sex: string
{
    case Male = 'male';
    case Female = 'female';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Male',
            self::Female => 'Female',
            self::Other => 'Other',
        };
    }
}

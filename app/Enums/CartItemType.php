<?php

namespace App\Enums;

enum CartItemType: string
{
    case Test = 'test';
    case Package = 'package';

    /** The cart_items column that holds this type's id. */
    public function column(): string
    {
        return match ($this) {
            self::Test => 'lab_test_id',
            self::Package => 'lab_package_id',
        };
    }
}

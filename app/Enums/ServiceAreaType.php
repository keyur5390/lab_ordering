<?php

namespace App\Enums;

/** How a service area is defined (client, 2026-10-29: all three are supported). */
enum ServiceAreaType: string
{
    /** A list of postcodes in one country. */
    case Postcodes = 'postcodes';

    /** Everything within a distance of a centre point. */
    case Radius = 'radius';

    /** A zone drawn on the map. */
    case Polygon = 'polygon';

    public function label(): string
    {
        return match ($this) {
            self::Postcodes => 'Postcode list',
            self::Radius => 'Radius',
            self::Polygon => 'Drawn zone',
        };
    }
}

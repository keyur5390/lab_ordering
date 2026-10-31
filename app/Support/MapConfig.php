<?php

namespace App\Support;

/**
 * Map settings for pages with a Leaflet map (see config/services.php "maps").
 */
final class MapConfig
{
    /**
     * @return array{tile_url: string, attribution: string, center: array{0: float, 1: float}, zoom: int}
     */
    public static function toArray(): array
    {
        $maps = config('services.maps');

        return [
            'tile_url' => $maps['tile_url'],
            'attribution' => $maps['attribution'],
            'center' => [(float) $maps['default_latitude'], (float) $maps['default_longitude']],
            'zoom' => (int) $maps['default_zoom'],
        ];
    }
}

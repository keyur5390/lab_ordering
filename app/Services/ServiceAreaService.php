<?php

namespace App\Services;

use App\Enums\ServiceAreaType;
use App\Models\Address;
use App\Models\ServiceArea;
use App\Support\Countries;
use Illuminate\Support\Facades\DB;

/**
 * Decides whether home collection is available at an address (PROJECT.md §6).
 * Geometry is done in PHP, not the database, to stay portable between MySQL and PostgreSQL.
 */
class ServiceAreaService
{
    /** Mean Earth radius in metres. */
    private const EARTH_RADIUS_METERS = 6_371_008.8;

    /**
     * The first active area covering the address, or null. Postcode areas are checked first;
     * radius and zone areas only apply to addresses with a map pin.
     */
    public function coveringArea(Address $address): ?ServiceArea
    {
        $byPostcode = ServiceArea::active()
            ->where('type', ServiceAreaType::Postcodes)
            ->where('country', $address->country)
            ->whereHas('postcodes', fn ($query) => $query->where('postcode', $address->postcode_normalized))
            ->orderBy('id')
            ->first();

        if ($byPostcode || ! $address->hasLocation()) {
            return $byPostcode;
        }

        return ServiceArea::active()
            ->whereIn('type', [ServiceAreaType::Radius, ServiceAreaType::Polygon])
            ->orderBy('id')
            ->get()
            ->first(fn (ServiceArea $area) => $this->containsPoint($area, (float) $address->latitude, (float) $address->longitude));
    }

    public function covers(Address $address): bool
    {
        return $this->coveringArea($address) !== null;
    }

    public function containsPoint(ServiceArea $area, float $latitude, float $longitude): bool
    {
        return match ($area->type) {
            ServiceAreaType::Radius => $this->distanceMeters(
                (float) $area->center_latitude, (float) $area->center_longitude, $latitude, $longitude,
            ) <= (int) $area->radius_meters,
            ServiceAreaType::Polygon => $this->inPolygon($area->polygon ?? [], $latitude, $longitude),
            ServiceAreaType::Postcodes => false,
        };
    }

    /** Great-circle (haversine) distance in metres. */
    public function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_METERS * asin(min(1, sqrt($a)));
    }

    /**
     * Ray-casting point-in-polygon test on [latitude, longitude] points. Zones are assumed
     * not to cross the ±180° meridian.
     *
     * @param  list<array{0: float, 1: float}>  $polygon
     */
    public function inPolygon(array $polygon, float $latitude, float $longitude): bool
    {
        $inside = false;
        $count = count($polygon);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$latI, $lngI] = $polygon[$i];
            [$latJ, $lngJ] = $polygon[$j];

            if (($latI > $latitude) !== ($latJ > $latitude)
                && $longitude < ($lngJ - $lngI) * ($latitude - $latI) / ($latJ - $latI) + $lngI) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /**
     * Replaces a postcode area's postcodes with the given list (normalised and de-duplicated).
     *
     * @param  list<string>  $postcodes
     */
    public function syncPostcodes(ServiceArea $area, array $postcodes): void
    {
        $normalized = collect($postcodes)
            ->map(fn (string $postcode) => Countries::normalizePostcode($postcode))
            ->filter()
            ->unique()
            ->values();

        DB::transaction(function () use ($area, $normalized) {
            $area->postcodes()->delete();
            $area->postcodes()->createMany($normalized->map(fn (string $postcode) => ['postcode' => $postcode])->all());
        });
    }
}

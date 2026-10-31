import { BaseMap, round } from '@/components/map/base-map';
import { CoordinateFields, toPoint } from '@/components/map/coordinate-fields';
import { Button } from '@/components/ui/button';
import { cssVar } from '@/lib/utils';
import type { MapConfig } from '@/types';
import { LocateFixed } from 'lucide-react';
import { useState } from 'react';
import { CircleMarker } from 'react-leaflet';

interface LocationPickerProps {
    config: MapConfig;
    latitude: string;
    longitude: string;
    onChange: (latitude: string, longitude: string) => void;
    errors: { latitude?: string; longitude?: string };
}

/** Optional map pin for an address: tap the map, use the device location, or type coordinates. */
export function LocationPicker({ config, latitude, longitude, onChange, errors }: LocationPickerProps) {
    const [initialCenter] = useState(() => toPoint(latitude, longitude));
    const [locating, setLocating] = useState(false);
    const [locateError, setLocateError] = useState<string | null>(null);
    const point = toPoint(latitude, longitude);

    const useMyLocation = () => {
        if (!navigator.geolocation) {
            setLocateError('Your browser cannot share its location.');
            return;
        }

        setLocating(true);
        setLocateError(null);
        navigator.geolocation.getCurrentPosition(
            (position) => {
                setLocating(false);
                onChange(String(round(position.coords.latitude)), String(round(position.coords.longitude)));
            },
            () => {
                setLocating(false);
                setLocateError('We could not get your location. Tap the map instead.');
            },
            { enableHighAccuracy: true, timeout: 10_000 },
        );
    };

    return (
        <div className="space-y-3">
            <BaseMap
                config={config}
                center={initialCenter}
                label="Map: tap to place the pin for this address"
                onClick={([lat, lng]) => onChange(String(lat), String(lng))}
            >
                {point && (
                    <CircleMarker center={point} radius={9} pathOptions={{ color: cssVar('--color-primary-600') }} />
                )}
            </BaseMap>

            <div className="flex flex-wrap items-center gap-2">
                <Button variant="secondary" size="sm" onClick={useMyLocation} processing={locating}>
                    <LocateFixed className="size-4" aria-hidden="true" />
                    Use my location
                </Button>
                {point && (
                    <Button variant="ghost" size="sm" onClick={() => onChange('', '')}>
                        Remove pin
                    </Button>
                )}
            </div>
            {locateError && (
                <p role="alert" className="text-sm text-danger-600">
                    {locateError}
                </p>
            )}

            <CoordinateFields
                idPrefix=""
                latitude={latitude}
                longitude={longitude}
                onChange={onChange}
                errors={errors}
            />
        </div>
    );
}

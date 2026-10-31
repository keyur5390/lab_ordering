import { BaseMap } from '@/components/map/base-map';
import { CoordinateFields, toPoint } from '@/components/map/coordinate-fields';
import { Button } from '@/components/ui/button';
import { cssVar } from '@/lib/utils';
import type { LatLng, MapConfig } from '@/types';
import { useState } from 'react';
import { Circle, CircleMarker, Polygon, Polyline } from 'react-leaflet';

interface RadiusEditorProps {
    config: MapConfig;
    latitude: string;
    longitude: string;
    /** Radius in km, as typed. */
    radiusKm: string;
    onCenterChange: (latitude: string, longitude: string) => void;
    errors: { latitude?: string; longitude?: string };
}

/** Radius area: tap the map (or type coordinates) to set the centre; the circle previews the radius. */
export function RadiusEditor({ config, latitude, longitude, radiusKm, onCenterChange, errors }: RadiusEditorProps) {
    const [initialCenter] = useState(() => toPoint(latitude, longitude));
    const center = toPoint(latitude, longitude);
    const radiusMeters = Number.parseFloat(radiusKm) * 1000;
    const color = cssVar('--color-primary-600');

    return (
        <div className="space-y-3">
            <BaseMap
                config={config}
                center={initialCenter}
                zoom={initialCenter ? 10 : undefined}
                label="Map: tap to set the centre of the area"
                onClick={([lat, lng]) => onCenterChange(String(lat), String(lng))}
            >
                {center && <CircleMarker center={center} radius={6} pathOptions={{ color }} />}
                {center && radiusMeters > 0 && <Circle center={center} radius={radiusMeters} pathOptions={{ color }} />}
            </BaseMap>
            <CoordinateFields
                idPrefix="center_"
                latitude={latitude}
                longitude={longitude}
                onChange={onCenterChange}
                errors={errors}
            />
        </div>
    );
}

interface PolygonEditorProps {
    config: MapConfig;
    points: LatLng[];
    onChange: (points: LatLng[]) => void;
}

/** Drawn zone: each tap on the map adds a corner; at least three corners make a zone. */
export function PolygonEditor({ config, points, onChange }: PolygonEditorProps) {
    const [initialCenter] = useState<LatLng | null>(() => (points.length > 0 ? centroid(points) : null));
    const color = cssVar('--color-primary-600');

    return (
        <div className="space-y-3">
            <BaseMap
                config={config}
                center={initialCenter}
                zoom={initialCenter ? 11 : undefined}
                label="Map: tap to add corners of the zone"
                onClick={(point) => onChange([...points, point])}
            >
                {points.length >= 3 ? (
                    <Polygon positions={points} pathOptions={{ color }} />
                ) : (
                    points.length === 2 && <Polyline positions={points} pathOptions={{ color }} />
                )}
                {points.map((point, index) => (
                    <CircleMarker key={index} center={point} radius={5} pathOptions={{ color }} />
                ))}
            </BaseMap>

            <div className="flex flex-wrap items-center gap-2">
                <p className="mr-auto text-sm text-muted" aria-live="polite">
                    {points.length} {points.length === 1 ? 'corner' : 'corners'}
                    {points.length > 0 && points.length < 3 && ` · add ${3 - points.length} more`}
                </p>
                <Button
                    variant="secondary"
                    size="sm"
                    disabled={points.length === 0}
                    onClick={() => onChange(points.slice(0, -1))}
                >
                    Undo last corner
                </Button>
                <Button variant="ghost" size="sm" disabled={points.length === 0} onClick={() => onChange([])}>
                    Clear
                </Button>
            </div>
        </div>
    );
}

function centroid(points: LatLng[]): LatLng {
    const sum = points.reduce(([lat, lng], [pLat, pLng]) => [lat + pLat, lng + pLng], [0, 0]);

    return [sum[0] / points.length, sum[1] / points.length];
}

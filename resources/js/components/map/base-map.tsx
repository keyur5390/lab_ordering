import 'leaflet/dist/leaflet.css';

import type { LatLng, MapConfig } from '@/types';
import type { ReactNode } from 'react';
import { MapContainer, TileLayer, useMapEvents } from 'react-leaflet';

function ClickHandler({ onClick }: { onClick: (point: LatLng) => void }) {
    useMapEvents({
        click: (event) => onClick([round(event.latlng.lat), round(event.latlng.lng)]),
    });

    return null;
}

/** Seven decimal places (~1 cm), matching the database columns. */
export function round(value: number): number {
    return Math.round(value * 1e7) / 1e7;
}

interface BaseMapProps {
    config: MapConfig;
    /** Where to centre the map initially; defaults to the configured centre. */
    center?: LatLng | null;
    zoom?: number;
    onClick?: (point: LatLng) => void;
    /** Accessible name describing what tapping the map does. */
    label: string;
    children?: ReactNode;
}

/** Leaflet map with the configured tiles (config/services.php "maps"). */
export function BaseMap({ config, center, zoom, onClick, label, children }: BaseMapProps) {
    return (
        <div role="group" aria-label={label} className="overflow-hidden rounded-control border border-border">
            <MapContainer
                center={center ?? config.center}
                zoom={zoom ?? (center ? 13 : config.zoom)}
                scrollWheelZoom={false}
                className="z-0 h-72 w-full md:h-96"
            >
                <TileLayer url={config.tile_url} attribution={config.attribution} />
                {onClick && <ClickHandler onClick={onClick} />}
                {children}
            </MapContainer>
        </div>
    );
}

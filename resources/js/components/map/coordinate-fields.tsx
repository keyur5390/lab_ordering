import { FormField } from '@/components/form-field';

interface CoordinateFieldsProps {
    idPrefix: string;
    latitude: string;
    longitude: string;
    onChange: (latitude: string, longitude: string) => void;
    errors: { latitude?: string; longitude?: string };
}

/** Typed latitude/longitude, as an alternative to tapping the map. */
export function CoordinateFields({ idPrefix, latitude, longitude, onChange, errors }: CoordinateFieldsProps) {
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <FormField
                id={`${idPrefix}latitude`}
                label="Latitude"
                inputMode="decimal"
                value={latitude}
                onChange={(e) => onChange(e.target.value, longitude)}
                error={errors.latitude}
            />
            <FormField
                id={`${idPrefix}longitude`}
                label="Longitude"
                inputMode="decimal"
                value={longitude}
                onChange={(e) => onChange(latitude, e.target.value)}
                error={errors.longitude}
            />
        </div>
    );
}

/** Parses typed coordinates into a point, or null if either is missing or invalid. */
export function toPoint(latitude: string, longitude: string): [number, number] | null {
    const lat = Number.parseFloat(latitude);
    const lng = Number.parseFloat(longitude);

    return Number.isFinite(lat) && Number.isFinite(lng) ? [lat, lng] : null;
}

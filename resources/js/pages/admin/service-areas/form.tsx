import { Field } from '@/components/field';
import { FormField } from '@/components/form-field';
import { PolygonEditor, RadiusEditor } from '@/components/map/area-editor';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { InputError } from '@/components/ui/input-error';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { TextLink } from '@/components/ui/text-link';
import AdminLayout from '@/layouts/admin-layout';
import type { LatLng, MapConfig, Option, ServiceArea, ServiceAreaType } from '@/types';
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface ServiceAreaFormProps {
    area: ServiceArea | null;
    types: Option<ServiceAreaType>[];
    countries: Option[];
    map: MapConfig;
}

const typeHelp: Record<ServiceAreaType, string> = {
    postcodes: 'Addresses with one of these postcodes in the chosen country.',
    radius: 'Addresses whose map pin is within a distance of a centre point.',
    polygon: 'Addresses whose map pin is inside a zone you draw.',
};

export default function ServiceAreaForm({ area, types, countries, map }: ServiceAreaFormProps) {
    const { data, setData, post, put, processing, errors } = useForm({
        name: area?.name ?? '',
        type: area?.type ?? ('postcodes' as ServiceAreaType),
        is_active: area?.is_active ?? true,
        country: area?.country ?? '',
        postcodes: area?.postcodes?.join('\n') ?? '',
        center_latitude: area?.center?.[0]?.toString() ?? '',
        center_longitude: area?.center?.[1]?.toString() ?? '',
        radius_km: area?.radius_km?.toString() ?? '',
        polygon: area?.polygon ?? ([] as LatLng[]),
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (area) {
            put(route('admin.service-areas.update', area.id));
        } else {
            post(route('admin.service-areas.store'));
        }
    };

    // The server validates the parsed `postcode_list` and each `polygon.N`; show the first matching error.
    const firstError = (field: string) =>
        Object.entries(errors as Record<string, string>).find(
            ([key]) => key === field || key.startsWith(`${field}.`),
        )?.[1];
    const postcodeError = firstError('postcode_list');
    const polygonError = firstError('polygon');

    return (
        <AdminLayout title={area ? `Edit ${area.name}` : 'New service area'}>
            <form onSubmit={submit} className="max-w-3xl space-y-6" noValidate>
                <div className="space-y-4 rounded-card border border-border bg-surface p-4 md:p-6">
                    <FormField
                        id="name"
                        label="Name"
                        hint="For example: Central Mumbai, North London."
                        required
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        error={errors.name}
                    />
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="is_active"
                            checked={data.is_active}
                            onChange={(e) => setData('is_active', e.target.checked)}
                        />
                        <Label htmlFor="is_active" className="font-normal">
                            Published (addresses inside it can book home collection)
                        </Label>
                    </div>
                </div>

                <fieldset className="space-y-4 rounded-card border border-border bg-surface p-4 md:p-6">
                    <legend className="px-1 text-base font-semibold">How is this area defined?</legend>
                    <div className="grid gap-2 sm:grid-cols-3">
                        {types.map((option) => (
                            <label
                                key={option.value}
                                className="flex cursor-pointer gap-3 rounded-control border border-border p-3 has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50"
                            >
                                <input
                                    type="radio"
                                    name="type"
                                    value={option.value}
                                    checked={data.type === option.value}
                                    onChange={() => setData('type', option.value)}
                                    className="mt-0.5 size-4 accent-primary-600"
                                />
                                <span>
                                    <span className="block text-sm font-medium">{option.label}</span>
                                    <span className="block text-sm text-muted">{typeHelp[option.value]}</span>
                                </span>
                            </label>
                        ))}
                    </div>
                    <InputError message={errors.type} />

                    {data.type === 'postcodes' && (
                        <div className="space-y-4">
                            <Field id="country" label="Country" error={errors.country}>
                                {(control) => (
                                    <Select
                                        {...control}
                                        value={data.country}
                                        onChange={(e) => setData('country', e.target.value)}
                                    >
                                        <option value="" disabled>
                                            Choose…
                                        </option>
                                        {countries.map((option) => (
                                            <option key={option.value} value={option.value}>
                                                {option.label}
                                            </option>
                                        ))}
                                    </Select>
                                )}
                            </Field>
                            <Field
                                id="postcodes"
                                label="Postcodes"
                                hint="One per line, or separated by commas. Case, spaces and dashes don't matter."
                                error={postcodeError}
                            >
                                {(control) => (
                                    <Textarea
                                        {...control}
                                        rows={8}
                                        value={data.postcodes}
                                        onChange={(e) => setData('postcodes', e.target.value)}
                                    />
                                )}
                            </Field>
                        </div>
                    )}

                    {data.type === 'radius' && (
                        <div className="space-y-4">
                            <FormField
                                id="radius_km"
                                label="Radius (km)"
                                inputMode="decimal"
                                value={data.radius_km}
                                onChange={(e) => setData('radius_km', e.target.value)}
                                error={errors.radius_km}
                            />
                            <RadiusEditor
                                config={map}
                                latitude={data.center_latitude}
                                longitude={data.center_longitude}
                                radiusKm={data.radius_km}
                                onCenterChange={(latitude, longitude) =>
                                    setData((current) => ({
                                        ...current,
                                        center_latitude: latitude,
                                        center_longitude: longitude,
                                    }))
                                }
                                errors={{ latitude: errors.center_latitude, longitude: errors.center_longitude }}
                            />
                        </div>
                    )}

                    {data.type === 'polygon' && (
                        <div className="space-y-2">
                            <PolygonEditor
                                config={map}
                                points={data.polygon}
                                onChange={(points) => setData('polygon', points)}
                            />
                            <InputError message={polygonError} />
                        </div>
                    )}
                </fieldset>

                <div className="flex items-center gap-4">
                    <Button type="submit" processing={processing}>
                        {area ? 'Save area' : 'Create area'}
                    </Button>
                    <TextLink href={route('admin.service-areas.index')}>Cancel</TextLink>
                </div>
            </form>
        </AdminLayout>
    );
}

import { Field } from '@/components/field';
import { FormField } from '@/components/form-field';
import { LocationPicker } from '@/components/map/location-picker';
import { Button } from '@/components/ui/button';
import { Select } from '@/components/ui/select';
import { TextLink } from '@/components/ui/text-link';
import AppLayout from '@/layouts/app-layout';
import type { Address, MapConfig, Option } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface AddressFormProps {
    address: Address | null;
    countries: Option[];
    map: MapConfig;
}

export default function AddressForm({ address, countries, map }: AddressFormProps) {
    const { data, setData, post, put, processing, errors } = useForm({
        label: address?.label ?? '',
        line1: address?.line1 ?? '',
        line2: address?.line2 ?? '',
        city: address?.city ?? '',
        region: address?.region ?? '',
        postcode: address?.postcode ?? '',
        country: address?.country ?? '',
        latitude: address?.latitude?.toString() ?? '',
        longitude: address?.longitude?.toString() ?? '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (address) {
            put(route('addresses.update', address.id));
        } else {
            post(route('addresses.store'));
        }
    };

    const title = address ? 'Edit address' : 'Add address';

    return (
        <AppLayout>
            <Head title={title} />

            <div className="mx-auto max-w-2xl px-4 py-8 sm:px-6 md:py-12 lg:px-8">
                <TextLink href={route('addresses.index')} className="text-sm">
                    ← Addresses
                </TextLink>
                <h1 className="mt-4 text-2xl font-semibold md:text-3xl">{title}</h1>

                <form onSubmit={submit} className="mt-6 space-y-6" noValidate>
                    <div className="space-y-4 rounded-card border border-border bg-surface p-4 md:p-6">
                        <FormField
                            id="label"
                            label="Label (optional)"
                            hint="For example: Home, Work, Mum's place."
                            value={data.label}
                            onChange={(e) => setData('label', e.target.value)}
                            error={errors.label}
                        />
                        <FormField
                            id="line1"
                            label="Address line 1"
                            autoComplete="address-line1"
                            required
                            value={data.line1}
                            onChange={(e) => setData('line1', e.target.value)}
                            error={errors.line1}
                        />
                        <FormField
                            id="line2"
                            label="Address line 2 (optional)"
                            autoComplete="address-line2"
                            value={data.line2}
                            onChange={(e) => setData('line2', e.target.value)}
                            error={errors.line2}
                        />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                id="city"
                                label="City"
                                autoComplete="address-level2"
                                required
                                value={data.city}
                                onChange={(e) => setData('city', e.target.value)}
                                error={errors.city}
                            />
                            <FormField
                                id="region"
                                label="State / region (optional)"
                                autoComplete="address-level1"
                                value={data.region}
                                onChange={(e) => setData('region', e.target.value)}
                                error={errors.region}
                            />
                            <FormField
                                id="postcode"
                                label="Postcode"
                                autoComplete="postal-code"
                                required
                                value={data.postcode}
                                onChange={(e) => setData('postcode', e.target.value)}
                                error={errors.postcode}
                            />
                            <Field id="country" label="Country" error={errors.country}>
                                {(control) => (
                                    <Select
                                        {...control}
                                        autoComplete="country"
                                        required
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
                        </div>
                    </div>

                    <section className="space-y-3 rounded-card border border-border bg-surface p-4 md:p-6">
                        <div>
                            <h2 className="text-base font-semibold">Location on the map (optional)</h2>
                            <p className="mt-1 text-sm text-muted">
                                A pin helps our collector find you and lets us check coverage more accurately.
                            </p>
                        </div>
                        <LocationPicker
                            config={map}
                            latitude={data.latitude}
                            longitude={data.longitude}
                            onChange={(latitude, longitude) =>
                                setData((current) => ({ ...current, latitude, longitude }))
                            }
                            errors={{ latitude: errors.latitude, longitude: errors.longitude }}
                        />
                    </section>

                    <div className="flex items-center gap-4">
                        <Button type="submit" processing={processing}>
                            {address ? 'Save' : 'Add address'}
                        </Button>
                        <TextLink href={route('addresses.index')}>Cancel</TextLink>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}

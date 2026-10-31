import { Field } from '@/components/field';
import { FormField } from '@/components/form-field';
import { LocationPicker } from '@/components/map/location-picker';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { InputError } from '@/components/ui/input-error';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { TextLink } from '@/components/ui/text-link';
import AdminLayout from '@/layouts/admin-layout';
import type { Branch, MapConfig, Option } from '@/types';
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface BranchFormProps {
    branch: Branch | null;
    countries: Option[];
    map: MapConfig;
}

export default function BranchForm({ branch, countries, map }: BranchFormProps) {
    const { data, setData, post, processing, errors, transform } = useForm({
        name: branch?.name ?? '',
        line1: branch?.line1 ?? '',
        line2: branch?.line2 ?? '',
        city: branch?.city ?? '',
        region: branch?.region ?? '',
        postcode: branch?.postcode ?? '',
        country: branch?.country ?? '',
        latitude: branch?.latitude?.toString() ?? '',
        longitude: branch?.longitude?.toString() ?? '',
        phone: branch?.phone ?? '',
        email: branch?.email ?? '',
        directions: branch?.directions ?? '',
        photo: null as File | null,
        remove_photo: false,
        is_active: branch?.is_active ?? true,
    });

    // Files need a multipart POST; Laravel reads _method as PUT for updates.
    transform((form) => (branch ? { ...form, _method: 'put' } : form));

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(branch ? route('admin.branches.update', branch.id) : route('admin.branches.store'), {
            forceFormData: true,
        });
    };

    return (
        <AdminLayout title={branch ? `Edit ${branch.name}` : 'New branch'}>
            <TextLink href={route('admin.branches.index')} className="text-sm">
                ← Branches
            </TextLink>
            <form onSubmit={submit} noValidate className="mt-4 max-w-3xl space-y-6">
                <section className="space-y-4 rounded-card border border-border bg-surface p-4 md:p-6">
                    <FormField
                        id="name"
                        label="Name"
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
                            Published (customers can book lab visits here)
                        </Label>
                    </div>
                </section>

                <section className="space-y-4 rounded-card border border-border bg-surface p-4 md:p-6">
                    <h2 className="text-base font-semibold">Address</h2>
                    <FormField
                        id="line1"
                        label="Address line 1"
                        value={data.line1}
                        onChange={(e) => setData('line1', e.target.value)}
                        error={errors.line1}
                    />
                    <FormField
                        id="line2"
                        label="Address line 2 (optional)"
                        value={data.line2}
                        onChange={(e) => setData('line2', e.target.value)}
                        error={errors.line2}
                    />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            id="city"
                            label="City"
                            value={data.city}
                            onChange={(e) => setData('city', e.target.value)}
                            error={errors.city}
                        />
                        <FormField
                            id="region"
                            label="Region (optional)"
                            value={data.region}
                            onChange={(e) => setData('region', e.target.value)}
                            error={errors.region}
                        />
                        <FormField
                            id="postcode"
                            label="Postcode"
                            value={data.postcode}
                            onChange={(e) => setData('postcode', e.target.value)}
                            error={errors.postcode}
                        />
                        <Field id="country" label="Country" error={errors.country}>
                            {(control) => (
                                <Select
                                    {...control}
                                    value={data.country}
                                    onChange={(e) => setData('country', e.target.value)}
                                >
                                    <option value="" disabled>
                                        Choose a country
                                    </option>
                                    {countries.map((country) => (
                                        <option key={country.value} value={country.value}>
                                            {country.label}
                                        </option>
                                    ))}
                                </Select>
                            )}
                        </Field>
                    </div>
                    <div>
                        <p className="mb-2 text-sm font-medium">Location on the map</p>
                        <LocationPicker
                            config={map}
                            latitude={data.latitude}
                            longitude={data.longitude}
                            onChange={(latitude, longitude) => setData((form) => ({ ...form, latitude, longitude }))}
                            errors={{ latitude: errors.latitude, longitude: errors.longitude }}
                        />
                    </div>
                </section>

                <section className="space-y-4 rounded-card border border-border bg-surface p-4 md:p-6">
                    <h2 className="text-base font-semibold">For customers</h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            id="phone"
                            label="Phone (optional)"
                            type="tel"
                            value={data.phone}
                            onChange={(e) => setData('phone', e.target.value)}
                            error={errors.phone}
                        />
                        <FormField
                            id="email"
                            label="Email (optional)"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            error={errors.email}
                        />
                    </div>
                    <Field
                        id="directions"
                        label="Directions (optional)"
                        hint="Parking, floor, landmarks…"
                        error={errors.directions}
                    >
                        {(control) => (
                            <Textarea
                                {...control}
                                rows={3}
                                value={data.directions}
                                onChange={(e) => setData('directions', e.target.value)}
                            />
                        )}
                    </Field>
                    <div className="space-y-2">
                        <Label htmlFor="photo">Photo (optional)</Label>
                        {branch?.photo_url && !data.remove_photo && !data.photo && (
                            <img src={branch.photo_url} alt="" className="h-32 rounded-control object-cover" />
                        )}
                        <input
                            id="photo"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            className="block max-w-full text-sm file:mr-3 file:h-11 file:rounded-control file:border file:border-border file:bg-surface file:px-3 file:text-sm file:font-medium"
                            aria-describedby={errors.photo ? 'photo-error' : undefined}
                            onChange={(e) => setData('photo', e.target.files?.[0] ?? null)}
                        />
                        <InputError id="photo-error" message={errors.photo} />
                        {branch?.photo_url && (
                            <label className="flex min-h-11 items-center gap-2 text-sm">
                                <Checkbox
                                    checked={data.remove_photo}
                                    onChange={(e) => setData('remove_photo', e.target.checked)}
                                />
                                Remove the photo
                            </label>
                        )}
                    </div>
                </section>

                <Button type="submit" processing={processing}>
                    {branch ? 'Save branch' : 'Create branch'}
                </Button>
            </form>
        </AdminLayout>
    );
}

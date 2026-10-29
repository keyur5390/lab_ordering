import { Field } from '@/components/field';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Select } from '@/components/ui/select';
import AdminLayout from '@/layouts/admin-layout';
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface SettingsProps {
    currency: string | null;
    currencies: { value: string; label: string }[];
    timezone: string;
    timezones: string[];
    cancellationCutoffHours: number | null;
}

export default function Settings({
    currency,
    currencies,
    timezone,
    timezones,
    cancellationCutoffHours,
}: SettingsProps) {
    const { data, setData, put, processing, errors, isDirty } = useForm({
        currency: currency ?? '',
        timezone,
        cancellation_cutoff_hours: cancellationCutoffHours?.toString() ?? '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put(route('admin.settings.update'), { preserveScroll: true });
    };

    return (
        <AdminLayout title="Settings">
            <form onSubmit={submit} className="max-w-2xl space-y-6" noValidate>
                <section className="space-y-4 rounded-card border border-border bg-surface p-4 md:p-6">
                    <h2 className="text-base font-semibold">Currency</h2>

                    <Field
                        id="currency"
                        label="Site currency"
                        hint="All prices are shown and charged in this currency."
                        error={errors.currency}
                    >
                        {(control) => (
                            <Select
                                {...control}
                                value={data.currency}
                                onChange={(e) => setData('currency', e.target.value)}
                            >
                                <option value="" disabled>
                                    Choose a currency
                                </option>
                                {currencies.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </Select>
                        )}
                    </Field>

                    {currency && data.currency !== currency && (
                        <p
                            className="rounded-control border border-warning-600/30 p-3 text-sm text-warning-600"
                            role="alert"
                        >
                            Changing the currency does not convert existing prices. A price of 500 stays 500 in the new
                            currency, so review your tests and packages afterwards.
                        </p>
                    )}
                </section>

                <section className="space-y-4 rounded-card border border-border bg-surface p-4 md:p-6">
                    <h2 className="text-base font-semibold">Timezone</h2>
                    <Field
                        id="timezone"
                        label="Site timezone"
                        hint="Collection hours, slots and closures use this timezone."
                        error={errors.timezone}
                    >
                        {(control) => (
                            <Select
                                {...control}
                                value={data.timezone}
                                onChange={(e) => setData('timezone', e.target.value)}
                            >
                                {timezones.map((zone) => (
                                    <option key={zone} value={zone}>
                                        {zone.replaceAll('_', ' ')}
                                    </option>
                                ))}
                            </Select>
                        )}
                    </Field>
                </section>

                <section className="space-y-4 rounded-card border border-border bg-surface p-4 md:p-6">
                    <h2 className="text-base font-semibold">Orders</h2>
                    <FormField
                        id="cancellation_cutoff_hours"
                        label="Customers can cancel or reschedule up to (hours before the slot)"
                        hint="Leave empty to let only admins cancel or reschedule."
                        type="number"
                        inputMode="numeric"
                        min={0}
                        value={data.cancellation_cutoff_hours}
                        onChange={(e) => setData('cancellation_cutoff_hours', e.target.value)}
                        error={errors.cancellation_cutoff_hours}
                    />
                </section>

                <Button type="submit" processing={processing} disabled={!isDirty}>
                    Save settings
                </Button>
            </form>
        </AdminLayout>
    );
}

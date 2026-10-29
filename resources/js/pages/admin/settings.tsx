import { Field } from '@/components/field';
import { Button } from '@/components/ui/button';
import { Select } from '@/components/ui/select';
import AdminLayout from '@/layouts/admin-layout';
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface SettingsProps {
    currency: string | null;
    currencies: { value: string; label: string }[];
}

export default function Settings({ currency, currencies }: SettingsProps) {
    const { data, setData, put, processing, errors, isDirty } = useForm({ currency: currency ?? '' });

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

                <Button type="submit" processing={processing} disabled={!isDirty}>
                    Save settings
                </Button>
            </form>
        </AdminLayout>
    );
}

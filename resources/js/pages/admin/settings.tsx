import { Field } from '@/components/field';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Select } from '@/components/ui/select';
import { TextLink } from '@/components/ui/text-link';
import AdminLayout from '@/layouts/admin-layout';
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface SettingsProps {
    currency: string | null;
    currencies: { value: string; label: string }[];
    timezone: string;
    timezones: string[];
    cancellationCutoffHours: number | null;
    paymentGateway: string | null;
    gateways: { value: string; label: string; configured: boolean; env_keys: string }[];
    paymentHoldMinutes: number;
    notifyByEmail: boolean;
    smsDriver: string | null;
    smsDrivers: { value: string; label: string; configured: boolean; env_keys: string | null }[];
    reminderHoursBefore: number;
    adminNotificationEmail: string | null;
}

export default function Settings({
    currency,
    currencies,
    timezone,
    timezones,
    cancellationCutoffHours,
    paymentGateway,
    gateways,
    paymentHoldMinutes,
    notifyByEmail,
    smsDriver,
    smsDrivers,
    reminderHoursBefore,
    adminNotificationEmail,
}: SettingsProps) {
    const { data, setData, put, processing, errors, isDirty } = useForm({
        currency: currency ?? '',
        timezone,
        cancellation_cutoff_hours: cancellationCutoffHours?.toString() ?? '',
        payment_gateway: paymentGateway ?? '',
        payment_hold_minutes: paymentHoldMinutes.toString(),
        notify_by_email: notifyByEmail,
        sms_driver: smsDriver ?? '',
        reminder_hours_before: reminderHoursBefore.toString(),
        admin_notification_email: adminNotificationEmail ?? '',
    });
    const unconfigured = gateways.filter((gateway) => !gateway.configured);
    const unconfiguredSms = smsDrivers.filter((driver) => !driver.configured);

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

                <section className="space-y-4 rounded-card border border-border bg-surface p-4 md:p-6">
                    <h2 className="text-base font-semibold">Payments</h2>
                    <Field
                        id="payment_gateway"
                        label="Online payment gateway"
                        hint="Customers can always pay at collection. Choose a gateway to let them pay online too."
                        error={errors.payment_gateway}
                    >
                        {(control) => (
                            <Select
                                {...control}
                                value={data.payment_gateway}
                                onChange={(e) => setData('payment_gateway', e.target.value)}
                            >
                                <option value="">Off (pay at collection only)</option>
                                {gateways.map((gateway) => (
                                    <option key={gateway.value} value={gateway.value} disabled={!gateway.configured}>
                                        {gateway.label}
                                        {!gateway.configured && ' (keys not set)'}
                                    </option>
                                ))}
                            </Select>
                        )}
                    </Field>
                    {unconfigured.length > 0 && (
                        <ul className="space-y-1 text-sm text-muted">
                            {unconfigured.map((gateway) => (
                                <li key={gateway.value}>
                                    To use {gateway.label}, add {gateway.env_keys} to the server's .env file.
                                </li>
                            ))}
                        </ul>
                    )}
                    <FormField
                        id="payment_hold_minutes"
                        label="Hold the slot for unpaid online orders for (minutes)"
                        hint="Unpaid orders are cancelled after this, and their slot is released."
                        type="number"
                        inputMode="numeric"
                        min={5}
                        value={data.payment_hold_minutes}
                        onChange={(e) => setData('payment_hold_minutes', e.target.value)}
                        error={errors.payment_hold_minutes}
                    />
                </section>

                <section className="space-y-4 rounded-card border border-border bg-surface p-4 md:p-6">
                    <h2 className="text-base font-semibold">Notifications</h2>
                    <p className="text-sm text-muted">
                        Edit the wording in <TextLink href={route('admin.notifications.index')}>Notifications</TextLink>
                        .
                    </p>
                    <label className="flex min-h-11 items-center gap-3 text-sm">
                        <Checkbox
                            checked={data.notify_by_email}
                            onChange={(e) => setData('notify_by_email', e.target.checked)}
                        />
                        Email customers about their orders
                    </label>
                    <Field
                        id="sms_driver"
                        label="Text messages (SMS)"
                        hint="Sent to the phone on the customer's own profile, when it includes the country code. Customers can opt out."
                        error={errors.sms_driver}
                    >
                        {(control) => (
                            <Select
                                {...control}
                                value={data.sms_driver}
                                onChange={(e) => setData('sms_driver', e.target.value)}
                            >
                                <option value="">Off</option>
                                {smsDrivers.map((driver) => (
                                    <option key={driver.value} value={driver.value} disabled={!driver.configured}>
                                        {driver.label}
                                        {!driver.configured && ' (keys not set)'}
                                    </option>
                                ))}
                            </Select>
                        )}
                    </Field>
                    {unconfiguredSms.length > 0 && (
                        <ul className="space-y-1 text-sm text-muted">
                            {unconfiguredSms.map((driver) => (
                                <li key={driver.value}>
                                    To use {driver.label}, add {driver.env_keys} to the server's .env file.
                                </li>
                            ))}
                        </ul>
                    )}
                    <FormField
                        id="reminder_hours_before"
                        label="Send the collection reminder (hours before the slot)"
                        hint="0 turns reminders off."
                        type="number"
                        inputMode="numeric"
                        min={0}
                        value={data.reminder_hours_before}
                        onChange={(e) => setData('reminder_hours_before', e.target.value)}
                        error={errors.reminder_hours_before}
                    />
                    <FormField
                        id="admin_notification_email"
                        label="Email admin alerts to"
                        hint="New orders, cancellations and failed refunds. Leave empty to turn alerts off."
                        type="email"
                        value={data.admin_notification_email}
                        onChange={(e) => setData('admin_notification_email', e.target.value)}
                        error={errors.admin_notification_email}
                    />
                </section>

                <Button type="submit" processing={processing} disabled={!isDirty}>
                    Save settings
                </Button>
            </form>
        </AdminLayout>
    );
}

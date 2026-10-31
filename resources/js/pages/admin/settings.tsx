import { Field } from '@/components/field';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Select } from '@/components/ui/select';
import { TextLink } from '@/components/ui/text-link';
import { Textarea } from '@/components/ui/textarea';
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
    freeCollectionAbove: string | null;
    minimumOrder: string | null;
    taxRate: string | null;
    taxInclusive: boolean;
    invoiceSeller: { name: string; address: string | null; tax_id: string | null; footer: string | null };
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
    freeCollectionAbove,
    minimumOrder,
    taxRate,
    taxInclusive,
    invoiceSeller,
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
        free_collection_above: freeCollectionAbove ?? '',
        minimum_order: minimumOrder ?? '',
        tax_rate: taxRate ?? '',
        tax_inclusive: taxInclusive,
        invoice_name: invoiceSeller.name,
        invoice_address: invoiceSeller.address ?? '',
        invoice_tax_id: invoiceSeller.tax_id ?? '',
        invoice_footer: invoiceSeller.footer ?? '',
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
                    <h2 className="text-base font-semibold">Pricing</h2>
                    <p className="text-sm text-muted">
                        Each service area has its own home collection fee. Promo codes are under{' '}
                        <TextLink href={route('admin.coupons.index')}>Coupons</TextLink>.
                    </p>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            id="free_collection_above"
                            label="Free collection on orders from"
                            hint="Tests total after any promo code. Leave empty to always charge the fee."
                            inputMode="decimal"
                            value={data.free_collection_above}
                            onChange={(e) => setData('free_collection_above', e.target.value)}
                            error={errors.free_collection_above}
                        />
                        <FormField
                            id="minimum_order"
                            label="Minimum order"
                            hint="Tests total. Leave empty for no minimum."
                            inputMode="decimal"
                            value={data.minimum_order}
                            onChange={(e) => setData('minimum_order', e.target.value)}
                            error={errors.minimum_order}
                        />
                        <FormField
                            id="tax_rate"
                            label="Tax rate (%)"
                            hint="Leave empty for no tax."
                            inputMode="decimal"
                            value={data.tax_rate}
                            onChange={(e) => setData('tax_rate', e.target.value)}
                            error={errors.tax_rate}
                        />
                        <fieldset className="space-y-2">
                            <legend className="text-sm font-medium">Prices</legend>
                            {[
                                { value: true, label: 'Include tax (tax is shown as part of the total)' },
                                { value: false, label: 'Exclude tax (tax is added at checkout)' },
                            ].map((option) => (
                                <label key={String(option.value)} className="flex min-h-11 items-center gap-3 text-sm">
                                    <input
                                        type="radio"
                                        name="tax_inclusive"
                                        className="size-4 accent-primary-600"
                                        checked={data.tax_inclusive === option.value}
                                        onChange={() => setData('tax_inclusive', option.value)}
                                    />
                                    {option.label}
                                </label>
                            ))}
                        </fieldset>
                    </div>
                </section>

                <section className="space-y-4 rounded-card border border-border bg-surface p-4 md:p-6">
                    <h2 className="text-base font-semibold">Invoices</h2>
                    <p className="text-sm text-muted">
                        Printed on invoices and credit notes. Changes apply to new invoices only; issued ones keep what
                        they showed.
                    </p>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            id="invoice_name"
                            label="Business name"
                            value={data.invoice_name}
                            onChange={(e) => setData('invoice_name', e.target.value)}
                            error={errors.invoice_name}
                        />
                        <FormField
                            id="invoice_tax_id"
                            label="Tax ID (optional)"
                            hint="For example a GSTIN or VAT number."
                            value={data.invoice_tax_id}
                            onChange={(e) => setData('invoice_tax_id', e.target.value)}
                            error={errors.invoice_tax_id}
                        />
                    </div>
                    <Field id="invoice_address" label="Business address (optional)" error={errors.invoice_address}>
                        {(control) => (
                            <Textarea
                                {...control}
                                rows={3}
                                value={data.invoice_address}
                                onChange={(e) => setData('invoice_address', e.target.value)}
                            />
                        )}
                    </Field>
                    <FormField
                        id="invoice_footer"
                        label="Footer (optional)"
                        hint="For example payment terms or a thank-you line."
                        value={data.invoice_footer}
                        onChange={(e) => setData('invoice_footer', e.target.value)}
                        error={errors.invoice_footer}
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

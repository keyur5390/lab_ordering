import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { TextLink } from '@/components/ui/text-link';
import AdminLayout from '@/layouts/admin-layout';
import type { SharedData } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface CouponForm {
    id: number;
    code: string;
    description: string | null;
    discount_type: 'percent' | 'amount';
    discount_value: string;
    max_discount: string | null;
    min_order: string | null;
    starts_on: string | null;
    ends_on: string | null;
    usage_limit: number | null;
    per_customer_limit: number | null;
    is_active: boolean;
}

export default function CouponFormPage({ coupon }: { coupon: CouponForm | null }) {
    const { currency } = usePage<SharedData>().props;
    const { data, setData, post, put, processing, errors } = useForm({
        code: coupon?.code ?? '',
        description: coupon?.description ?? '',
        discount_type: coupon?.discount_type ?? ('percent' as 'percent' | 'amount'),
        discount_value: coupon?.discount_value ?? '',
        max_discount: coupon?.max_discount ?? '',
        min_order: coupon?.min_order ?? '',
        starts_on: coupon?.starts_on ?? '',
        ends_on: coupon?.ends_on ?? '',
        usage_limit: coupon?.usage_limit?.toString() ?? '',
        per_customer_limit: coupon?.per_customer_limit?.toString() ?? '',
        is_active: coupon?.is_active ?? true,
    });
    const percent = data.discount_type === 'percent';
    const code = currency?.code ?? '';

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (coupon) {
            put(route('admin.coupons.update', coupon.id));
        } else {
            post(route('admin.coupons.store'));
        }
    };

    return (
        <AdminLayout title={coupon ? `Edit ${coupon.code}` : 'New coupon'}>
            <TextLink href={route('admin.coupons.index')} className="text-sm">
                ← Coupons
            </TextLink>
            <form onSubmit={submit} noValidate className="mt-4 max-w-2xl space-y-6">
                <section className="space-y-4 rounded-card border border-border bg-surface p-4 md:p-6">
                    <FormField
                        id="code"
                        label="Code"
                        hint="What customers type, e.g. WELCOME10. Not case-sensitive."
                        autoCapitalize="characters"
                        value={data.code}
                        onChange={(e) => setData('code', e.target.value.toUpperCase())}
                        error={errors.code}
                    />
                    <FormField
                        id="description"
                        label="Description (optional)"
                        hint="Shown to customers when the code is applied."
                        value={data.description}
                        onChange={(e) => setData('description', e.target.value)}
                        error={errors.description}
                    />
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="is_active"
                            checked={data.is_active}
                            onChange={(e) => setData('is_active', e.target.checked)}
                        />
                        <Label htmlFor="is_active" className="font-normal">
                            On (customers can use it)
                        </Label>
                    </div>
                </section>

                <section className="space-y-4 rounded-card border border-border bg-surface p-4 md:p-6">
                    <fieldset className="space-y-2">
                        <legend className="text-base font-semibold">Discount</legend>
                        <div className="flex flex-wrap gap-4">
                            {(['percent', 'amount'] as const).map((type) => (
                                <label key={type} className="flex min-h-11 items-center gap-2 text-sm">
                                    <input
                                        type="radio"
                                        name="discount_type"
                                        className="size-4 accent-primary-600"
                                        checked={data.discount_type === type}
                                        onChange={() =>
                                            setData((form) => ({ ...form, discount_type: type, max_discount: '' }))
                                        }
                                    />
                                    {type === 'percent' ? 'Percentage off' : 'Fixed amount off'}
                                </label>
                            ))}
                        </div>
                    </fieldset>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            id="discount_value"
                            label={percent ? 'Percent off' : `Amount off (${code})`}
                            inputMode={percent ? 'numeric' : 'decimal'}
                            value={data.discount_value}
                            onChange={(e) => setData('discount_value', e.target.value)}
                            error={errors.discount_value}
                        />
                        {percent && (
                            <FormField
                                id="max_discount"
                                label={`At most (${code}, optional)`}
                                inputMode="decimal"
                                value={data.max_discount}
                                onChange={(e) => setData('max_discount', e.target.value)}
                                error={errors.max_discount}
                            />
                        )}
                        <FormField
                            id="min_order"
                            label={`Minimum tests total (${code}, optional)`}
                            inputMode="decimal"
                            value={data.min_order}
                            onChange={(e) => setData('min_order', e.target.value)}
                            error={errors.min_order}
                        />
                    </div>
                </section>

                <section className="space-y-4 rounded-card border border-border bg-surface p-4 md:p-6">
                    <h2 className="text-base font-semibold">Validity and limits</h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            id="starts_on"
                            label="From (optional)"
                            type="date"
                            value={data.starts_on}
                            onChange={(e) => setData('starts_on', e.target.value)}
                            error={errors.starts_on}
                        />
                        <FormField
                            id="ends_on"
                            label="Until (optional)"
                            type="date"
                            value={data.ends_on}
                            onChange={(e) => setData('ends_on', e.target.value)}
                            error={errors.ends_on}
                        />
                        <FormField
                            id="usage_limit"
                            label="Total uses (optional)"
                            type="number"
                            inputMode="numeric"
                            min={1}
                            value={data.usage_limit}
                            onChange={(e) => setData('usage_limit', e.target.value)}
                            error={errors.usage_limit}
                        />
                        <FormField
                            id="per_customer_limit"
                            label="Uses per customer (optional)"
                            type="number"
                            inputMode="numeric"
                            min={1}
                            value={data.per_customer_limit}
                            onChange={(e) => setData('per_customer_limit', e.target.value)}
                            error={errors.per_customer_limit}
                        />
                    </div>
                    <p className="text-sm text-muted">Cancelled orders give their use back.</p>
                </section>

                <Button type="submit" processing={processing}>
                    {coupon ? 'Save coupon' : 'Create coupon'}
                </Button>
            </form>
        </AdminLayout>
    );
}

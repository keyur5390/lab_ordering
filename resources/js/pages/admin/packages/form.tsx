import { CurrencyMissingNotice, useCurrencyHint } from '@/components/admin/currency-note';
import { Price } from '@/components/catalogue/price';
import { Field } from '@/components/field';
import { FormField } from '@/components/form-field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { InputError } from '@/components/ui/input-error';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { TextLink } from '@/components/ui/text-link';
import AdminLayout from '@/layouts/admin-layout';
import type { DiscountType, LabPackage, LabTest, PackagePricingMode } from '@/types';
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface PackageFormProps {
    package: LabPackage | null;
    /** Current money values as decimal strings (edit only). */
    form: { price: string; discount_value: string } | null;
    /** Every test, published or not, to choose from. */
    tests: LabTest[];
    minTests: number;
}

export default function PackageForm({ package: pkg, form, tests, minTests }: PackageFormProps) {
    const currencyHint = useCurrencyHint();
    const { data, setData, post, put, processing, errors } = useForm({
        name: pkg?.name ?? '',
        code: pkg?.code ?? '',
        description: pkg?.description ?? '',
        is_active: pkg?.is_active ?? true,
        test_ids: pkg?.tests?.map((test) => test.id) ?? ([] as number[]),
        pricing_mode: pkg?.pricing_mode ?? ('fixed' as PackagePricingMode),
        price: form?.price ?? '',
        discount_type: pkg?.discount_type ?? ('percent' as DiscountType),
        discount_value: form?.discount_value ?? '',
    });

    const selectedTotal = tests
        .filter((test) => data.test_ids.includes(test.id))
        .reduce((sum, test) => sum + test.price_amount, 0);

    const toggleTest = (id: number, checked: boolean) => {
        setData('test_ids', checked ? [...data.test_ids, id] : data.test_ids.filter((testId) => testId !== id));
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (pkg) {
            put(route('admin.packages.update', pkg.slug));
        } else {
            post(route('admin.packages.store'));
        }
    };

    const testIdsError = errors.test_ids ?? Object.entries(errors).find(([key]) => key.startsWith('test_ids.'))?.[1];

    return (
        <AdminLayout title={pkg ? `Edit ${pkg.name}` : 'New package'}>
            <form onSubmit={submit} className="max-w-2xl space-y-6" noValidate>
                <CurrencyMissingNotice />

                <div className="space-y-4 rounded-card border border-border bg-surface p-4 md:p-6">
                    <FormField
                        id="name"
                        label="Name"
                        required
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        error={errors.name}
                    />
                    <FormField
                        id="code"
                        label="Code"
                        hint="Unique. Letters, numbers, - and _."
                        required
                        value={data.code}
                        onChange={(e) => setData('code', e.target.value)}
                        error={errors.code}
                    />
                    <Field id="description" label="Description" error={errors.description}>
                        {(control) => (
                            <Textarea
                                {...control}
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                            />
                        )}
                    </Field>
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="is_active"
                            checked={data.is_active}
                            onChange={(e) => setData('is_active', e.target.checked)}
                        />
                        <Label htmlFor="is_active" className="font-normal">
                            Published (visible to customers)
                        </Label>
                    </div>
                </div>

                <fieldset
                    className="rounded-card border border-border bg-surface p-4 md:p-6"
                    aria-describedby={testIdsError ? 'test_ids-error' : undefined}
                >
                    <legend className="px-1 text-base font-semibold">Tests included</legend>
                    <p className="text-sm text-muted">Choose at least {minTests}.</p>

                    {tests.length === 0 ? (
                        <p className="mt-4 text-sm text-muted">
                            No tests yet. <TextLink href={route('admin.tests.create')}>Add a test</TextLink> first.
                        </p>
                    ) : (
                        <ul className="mt-4 max-h-80 divide-y divide-border overflow-y-auto rounded-control border border-border">
                            {tests.map((test) => (
                                <li key={test.id}>
                                    <label className="flex min-h-11 cursor-pointer items-center gap-3 px-3 py-2 hover:bg-canvas">
                                        <Checkbox
                                            checked={data.test_ids.includes(test.id)}
                                            onChange={(e) => toggleTest(test.id, e.target.checked)}
                                        />
                                        <span className="flex-1 text-sm">
                                            {test.name}
                                            {!test.is_active && (
                                                <Badge className="ml-2" aria-label="hidden from customers">
                                                    Hidden
                                                </Badge>
                                            )}
                                        </span>
                                        <Price amount={test.price_amount} className="text-sm" />
                                    </label>
                                </li>
                            ))}
                        </ul>
                    )}

                    <InputError id="test_ids-error" message={testIdsError} className="mt-2" />
                    <p className="mt-3 text-sm text-muted">
                        {data.test_ids.length} selected · tests total <Price amount={selectedTotal} />
                    </p>
                </fieldset>

                <fieldset className="space-y-4 rounded-card border border-border bg-surface p-4 md:p-6">
                    <legend className="px-1 text-base font-semibold">Pricing</legend>

                    <div className="space-y-2" role="radiogroup" aria-label="Pricing mode">
                        {(
                            [
                                ['fixed', 'Fixed price', 'Set the package price yourself.'],
                                [
                                    'discounted',
                                    'Discount on tests',
                                    "The tests' total minus a discount; follows test price changes.",
                                ],
                            ] as const
                        ).map(([value, label, description]) => (
                            <label
                                key={value}
                                className="flex cursor-pointer gap-3 rounded-control border border-border p-3 has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50"
                            >
                                <input
                                    type="radio"
                                    name="pricing_mode"
                                    value={value}
                                    checked={data.pricing_mode === value}
                                    onChange={() => setData('pricing_mode', value)}
                                    className="mt-0.5 size-4 accent-primary-600"
                                />
                                <span>
                                    <span className="block text-sm font-medium">{label}</span>
                                    <span className="block text-sm text-muted">{description}</span>
                                </span>
                            </label>
                        ))}
                    </div>
                    <InputError message={errors.pricing_mode} />

                    {data.pricing_mode === 'fixed' ? (
                        <FormField
                            id="price"
                            label="Package price"
                            hint={currencyHint}
                            inputMode="decimal"
                            placeholder="0.00"
                            required
                            value={data.price}
                            onChange={(e) => setData('price', e.target.value)}
                            error={errors.price}
                        />
                    ) : (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field id="discount_type" label="Discount type" error={errors.discount_type}>
                                {(control) => (
                                    <Select
                                        {...control}
                                        value={data.discount_type}
                                        onChange={(e) => setData('discount_type', e.target.value as DiscountType)}
                                    >
                                        <option value="percent">Percentage off</option>
                                        <option value="amount">Amount off</option>
                                    </Select>
                                )}
                            </Field>
                            <FormField
                                id="discount_value"
                                label={data.discount_type === 'percent' ? 'Discount (%)' : 'Discount amount'}
                                hint={data.discount_type === 'percent' ? 'Whole number from 1 to 100.' : currencyHint}
                                inputMode={data.discount_type === 'percent' ? 'numeric' : 'decimal'}
                                required
                                value={data.discount_value}
                                onChange={(e) => setData('discount_value', e.target.value)}
                                error={errors.discount_value}
                            />
                        </div>
                    )}
                </fieldset>

                <div className="flex items-center gap-4">
                    <Button type="submit" processing={processing}>
                        {pkg ? 'Save package' : 'Create package'}
                    </Button>
                    <TextLink href={route('admin.packages.index')}>Cancel</TextLink>
                </div>
            </form>
        </AdminLayout>
    );
}

import { CurrencyMissingNotice, useCurrencyHint } from '@/components/admin/currency-note';
import { Field } from '@/components/field';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { TextLink } from '@/components/ui/text-link';
import AdminLayout from '@/layouts/admin-layout';
import type { LabTest } from '@/types';
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface TestFormProps {
    test: LabTest | null;
    /** Current price as a decimal string, e.g. "499.50". */
    price: string;
}

export default function TestForm({ test, price }: TestFormProps) {
    const priceHint = useCurrencyHint();
    const { data, setData, post, put, processing, errors } = useForm({
        name: test?.name ?? '',
        code: test?.code ?? '',
        sample_type: test?.sample_type ?? '',
        turnaround_hours: test ? String(test.turnaround_hours) : '',
        price,
        description: test?.description ?? '',
        preparation: test?.preparation ?? '',
        is_active: test?.is_active ?? true,
        requires_prescription: test?.requires_prescription ?? false,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (test) {
            put(route('admin.tests.update', test.slug));
        } else {
            post(route('admin.tests.store'));
        }
    };

    return (
        <AdminLayout title={test ? `Edit ${test.name}` : 'New test'}>
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

                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            id="code"
                            label="Code"
                            hint="Unique. Letters, numbers, - and _."
                            required
                            value={data.code}
                            onChange={(e) => setData('code', e.target.value)}
                            error={errors.code}
                        />
                        <FormField
                            id="sample_type"
                            label="Sample type"
                            hint="For example: Blood, Urine."
                            required
                            value={data.sample_type}
                            onChange={(e) => setData('sample_type', e.target.value)}
                            error={errors.sample_type}
                        />
                        <FormField
                            id="turnaround_hours"
                            label="Turnaround (hours)"
                            type="number"
                            inputMode="numeric"
                            min={1}
                            required
                            value={data.turnaround_hours}
                            onChange={(e) => setData('turnaround_hours', e.target.value)}
                            error={errors.turnaround_hours}
                        />
                        <FormField
                            id="price"
                            label="Price"
                            hint={priceHint}
                            inputMode="decimal"
                            placeholder="0.00"
                            required
                            value={data.price}
                            onChange={(e) => setData('price', e.target.value)}
                            error={errors.price}
                        />
                    </div>

                    <Field id="description" label="Description" error={errors.description}>
                        {(control) => (
                            <Textarea
                                {...control}
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                            />
                        )}
                    </Field>

                    <Field
                        id="preparation"
                        label="Preparation instructions"
                        hint="For example: fasting for 10–12 hours. Leave empty if none."
                        error={errors.preparation}
                    >
                        {(control) => (
                            <Textarea
                                {...control}
                                rows={3}
                                value={data.preparation}
                                onChange={(e) => setData('preparation', e.target.value)}
                            />
                        )}
                    </Field>

                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="requires_prescription"
                            checked={data.requires_prescription}
                            onChange={(e) => setData('requires_prescription', e.target.checked)}
                        />
                        <Label htmlFor="requires_prescription" className="font-normal">
                            Requires a prescription (customers must upload one at checkout)
                        </Label>
                    </div>

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

                <div className="flex items-center gap-4">
                    <Button type="submit" processing={processing}>
                        {test ? 'Save test' : 'Create test'}
                    </Button>
                    <TextLink href={route('admin.tests.index')}>Cancel</TextLink>
                </div>
            </form>
        </AdminLayout>
    );
}

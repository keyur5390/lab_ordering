import { Field } from '@/components/field';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { InputError } from '@/components/ui/input-error';
import { Select } from '@/components/ui/select';
import { TextLink } from '@/components/ui/text-link';
import AppLayout from '@/layouts/app-layout';
import type { Option, Patient, PatientRelationship, Sex } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface PatientFormProps {
    patient: Patient | null;
    /** Relationships a customer can pick (not "Self", which only the sign-up profile has). */
    relationships: Option<PatientRelationship>[];
    sexes: Option<Sex>[];
}

/** Today's date as YYYY-MM-DD in the browser's timezone, for the date input's max. */
function today(): string {
    const now = new Date();
    return new Date(now.getTime() - now.getTimezoneOffset() * 60_000).toISOString().slice(0, 10);
}

export default function PatientForm({ patient, relationships, sexes }: PatientFormProps) {
    const isSelf = patient?.is_self ?? false;
    const { data, setData, transform, post, put, processing, errors } = useForm({
        name: patient?.name ?? '',
        relationship: (patient && !isSelf ? patient.relationship : '') as PatientRelationship | '',
        date_of_birth: patient?.date_of_birth ?? '',
        sex: (patient?.sex ?? '') as Sex | '',
        phone: patient?.phone ?? '',
        email: patient?.email ?? '',
    });

    // The "Self" profile's relationship is fixed, so it isn't sent.
    transform(({ relationship, ...rest }) => (isSelf ? rest : { relationship, ...rest }));

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (patient) {
            put(route('patients.update', patient.id));
        } else {
            post(route('patients.store'));
        }
    };

    const title = patient ? (isSelf ? 'Your details' : `Edit ${patient.name}`) : 'Add patient';

    return (
        <AppLayout>
            <Head title={title} />

            <div className="mx-auto max-w-2xl px-4 py-8 sm:px-6 md:py-12 lg:px-8">
                <TextLink href={route('patients.index')} className="text-sm">
                    ← Patients
                </TextLink>
                <h1 className="mt-4 text-2xl font-semibold md:text-3xl">{title}</h1>
                {isSelf && <p className="mt-1 text-sm text-muted">This profile is you, the account holder.</p>}

                <form onSubmit={submit} className="mt-6 space-y-6" noValidate>
                    <div className="space-y-4 rounded-card border border-border bg-surface p-4 md:p-6">
                        <FormField
                            id="name"
                            label="Full name"
                            autoComplete={isSelf ? 'name' : 'off'}
                            required
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            error={errors.name}
                        />

                        {!isSelf && (
                            <Field id="relationship" label="Relationship to you" error={errors.relationship}>
                                {(control) => (
                                    <Select
                                        {...control}
                                        required
                                        value={data.relationship}
                                        onChange={(e) => setData('relationship', e.target.value as PatientRelationship)}
                                    >
                                        <option value="" disabled>
                                            Choose…
                                        </option>
                                        {relationships.map((option) => (
                                            <option key={option.value} value={option.value}>
                                                {option.label}
                                            </option>
                                        ))}
                                    </Select>
                                )}
                            </Field>
                        )}

                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                id="date_of_birth"
                                label="Date of birth"
                                type="date"
                                min="1900-01-01"
                                max={today()}
                                required
                                value={data.date_of_birth}
                                onChange={(e) => setData('date_of_birth', e.target.value)}
                                error={errors.date_of_birth}
                            />

                            <fieldset aria-describedby={errors.sex ? 'sex-error' : undefined}>
                                <legend className="text-sm font-medium">Sex</legend>
                                <div className="mt-1.5 flex flex-wrap gap-2">
                                    {sexes.map((option) => (
                                        <label
                                            key={option.value}
                                            className="flex h-11 cursor-pointer items-center gap-2 rounded-control border border-border px-3 text-sm has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50"
                                        >
                                            <input
                                                type="radio"
                                                name="sex"
                                                value={option.value}
                                                checked={data.sex === option.value}
                                                onChange={() => setData('sex', option.value)}
                                                className="size-4 accent-primary-600"
                                            />
                                            {option.label}
                                        </label>
                                    ))}
                                </div>
                                <InputError id="sex-error" message={errors.sex} className="mt-1.5" />
                            </fieldset>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                id="phone"
                                label="Phone (optional)"
                                type="tel"
                                inputMode="tel"
                                autoComplete={isSelf ? 'tel' : 'off'}
                                value={data.phone}
                                onChange={(e) => setData('phone', e.target.value)}
                                error={errors.phone}
                            />
                            <FormField
                                id="email"
                                label="Email (optional)"
                                type="email"
                                autoComplete={isSelf ? 'email' : 'off'}
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                error={errors.email}
                            />
                        </div>
                    </div>

                    <div className="flex items-center gap-4">
                        <Button type="submit" processing={processing}>
                            {patient ? 'Save' : 'Add patient'}
                        </Button>
                        <TextLink href={route('patients.index')}>Cancel</TextLink>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}

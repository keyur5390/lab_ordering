import { DeleteButton } from '@/components/delete-button';
import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { TextLink } from '@/components/ui/text-link';
import AppLayout from '@/layouts/app-layout';
import type { Patient, SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Users } from 'lucide-react';

const primaryLinkClass =
    'inline-flex h-11 items-center justify-center rounded-control bg-primary-600 px-4 text-sm font-medium whitespace-nowrap text-white hover:bg-primary-700';

function details(patient: Patient): string {
    return [
        patient.relationship_label,
        patient.age !== null && `${patient.age} ${patient.age === 1 ? 'year' : 'years'}`,
        patient.sex_label,
    ]
        .filter(Boolean)
        .join(' · ');
}

export default function PatientsIndex({ patients }: { patients: Patient[] }) {
    const { status } = usePage<SharedData>().props;

    return (
        <AppLayout>
            <Head title="Patients" />

            <div className="mx-auto max-w-3xl px-4 py-8 sm:px-6 md:py-12 lg:px-8">
                <TextLink href={route('account')} className="text-sm">
                    ← Your account
                </TextLink>

                <div className="mt-4 flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold md:text-3xl">Patients</h1>
                        <p className="mt-1 text-sm text-muted">The people you book tests for, including yourself.</p>
                    </div>
                    <Link href={route('patients.create')} className={primaryLinkClass}>
                        Add patient
                    </Link>
                </div>

                {status && (
                    <Alert tone="success" className="mt-4">
                        {status}
                    </Alert>
                )}

                {patients.length === 0 ? (
                    <div className="mt-6 flex flex-col items-center rounded-card border border-border bg-surface p-8 text-center">
                        <Users className="size-6 text-muted" strokeWidth={1.75} aria-hidden="true" />
                        <p className="mt-3 text-sm text-muted">No patients yet.</p>
                    </div>
                ) : (
                    <ul className="mt-6 divide-y divide-border rounded-card border border-border bg-surface">
                        {patients.map((patient) => (
                            <li key={patient.id} className="flex items-center justify-between gap-3 p-4">
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-medium">{patient.name}</span>
                                        {!patient.is_complete && <Badge tone="warning">Details needed</Badge>}
                                    </div>
                                    <p className="text-sm text-muted">{details(patient)}</p>
                                </div>
                                <div className="flex shrink-0 items-center gap-1">
                                    <Link
                                        href={route('patients.edit', patient.id)}
                                        className="inline-flex h-11 items-center rounded-control px-3 text-sm font-medium text-primary-700 hover:bg-canvas sm:h-9"
                                        aria-label={`Edit ${patient.name}`}
                                    >
                                        Edit
                                    </Link>
                                    {!patient.is_self && (
                                        <DeleteButton
                                            href={route('patients.destroy', patient.id)}
                                            itemName={patient.name}
                                            itemType="patient"
                                            verb="Remove"
                                        />
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AppLayout>
    );
}

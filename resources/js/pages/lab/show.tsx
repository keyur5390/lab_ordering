import { OrderStatusBadge } from '@/components/order-status-badge';
import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { InputError } from '@/components/ui/input-error';
import { TextLink } from '@/components/ui/text-link';
import AdminLayout from '@/layouts/admin-layout';
import { formatDate, formatDateTime, formatSlot } from '@/lib/format';
import type { OrderStatus, SharedData } from '@/types';
import { router, useForm, usePage } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface LabItem {
    id: number;
    patient_name: string;
    patient_date_of_birth: string;
    patient_sex: string;
    name: string;
    code: string;
    test_names: string[] | null;
    report_name: string | null;
    report_uploaded_at: string | null;
}

interface LabOrder {
    number: string;
    status: OrderStatus;
    status_label: string;
    slot_starts_at: string;
    slot_ends_at: string;
    collected_at: string | null;
    received_at_lab_at: string | null;
    report_ready_at: string | null;
    timezone: string;
    has_prescription: boolean;
    prescription_name: string | null;
    items: LabItem[];
    can_mark_collected: boolean;
    can_mark_received: boolean;
    can_upload_reports: boolean;
}

function when(iso: string | null, timeZone: string): string {
    return iso ? formatDateTime(iso, timeZone) : '—';
}

function ReportUpload({ order, item }: { order: LabOrder; item: LabItem }) {
    const { data, setData, post, processing, errors, reset } = useForm({ report: null as File | null });
    // Remounts the file input after an upload so it no longer shows the sent file.
    const [inputKey, setInputKey] = useState(0);
    const inputId = `report-${item.id}`;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('admin.lab.reports.store', [order.number, item.id]), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setInputKey((key) => key + 1);
            },
        });
    };

    return (
        <form onSubmit={submit} className="mt-2 flex flex-wrap items-center gap-2">
            <label htmlFor={inputId} className="sr-only">
                Report PDF for {item.patient_name}, {item.name}
            </label>
            <input
                key={inputKey}
                id={inputId}
                type="file"
                accept="application/pdf,.pdf"
                className="max-w-full text-sm file:mr-3 file:h-11 file:rounded-control file:border file:border-border file:bg-surface file:px-3 file:text-sm file:font-medium sm:file:h-9"
                aria-describedby={errors.report ? `${inputId}-error` : undefined}
                onChange={(e) => setData('report', e.target.files?.[0] ?? null)}
            />
            <Button type="submit" size="sm" variant="secondary" processing={processing} disabled={!data.report}>
                {item.report_name ? 'Replace report' : 'Upload report'}
            </Button>
            <InputError id={`${inputId}-error`} message={errors.report} className="w-full" />
        </form>
    );
}

export default function LabShow({ order }: { order: LabOrder }) {
    const { errors } = usePage<SharedData & { errors: Record<string, string> }>().props;
    const [processing, setProcessing] = useState(false);
    const reportsIn = order.items.filter((item) => item.report_name).length;

    const step = (routeName: 'admin.lab.collect' | 'admin.lab.receive') =>
        router.post(
            route(routeName, order.number),
            {},
            { preserveScroll: true, onStart: () => setProcessing(true), onFinish: () => setProcessing(false) },
        );

    // Grouped by patient, in booking order.
    const patients = order.items.reduce<{ key: string; patient: LabItem; items: LabItem[] }[]>((groups, item) => {
        const key = `${item.patient_name}|${item.patient_date_of_birth}`;
        const group = groups.find((g) => g.key === key);
        if (group) {
            group.items.push(item);
        } else {
            groups.push({ key, patient: item, items: [item] });
        }

        return groups;
    }, []);

    return (
        <AdminLayout
            title={`Order ${order.number}`}
            actions={
                <div className="flex flex-wrap gap-2">
                    {order.can_mark_collected && (
                        <Button processing={processing} onClick={() => step('admin.lab.collect')}>
                            Mark sample collected
                        </Button>
                    )}
                    {order.can_mark_received && (
                        <Button processing={processing} onClick={() => step('admin.lab.receive')}>
                            Mark received at lab
                        </Button>
                    )}
                </div>
            }
        >
            <TextLink href={route('admin.lab.index')} className="text-sm">
                ← Lab
            </TextLink>

            {errors.status && (
                <Alert tone="danger" className="mt-4">
                    {errors.status}
                </Alert>
            )}

            <div className="mt-4 max-w-3xl space-y-6">
                <div className="flex flex-wrap items-center gap-3">
                    <OrderStatusBadge status={order.status} label={order.status_label} />
                    <span className="text-sm text-muted">
                        Reports {reportsIn} of {order.items.length}
                    </span>
                    <TextLink href={route('admin.orders.show', order.number)} className="text-sm">
                        Full order
                    </TextLink>
                </div>

                <dl className="grid gap-x-6 gap-y-3 rounded-card border border-border bg-surface p-4 text-sm sm:grid-cols-2 md:p-6">
                    <div>
                        <dt className="text-muted">Collection slot</dt>
                        <dd>{formatSlot(order.slot_starts_at, order.slot_ends_at, order.timezone)}</dd>
                    </div>
                    <div>
                        <dt className="text-muted">Sample collected</dt>
                        <dd>{when(order.collected_at, order.timezone)}</dd>
                    </div>
                    <div>
                        <dt className="text-muted">Received at lab</dt>
                        <dd>{when(order.received_at_lab_at, order.timezone)}</dd>
                    </div>
                    <div>
                        <dt className="text-muted">All reports in</dt>
                        <dd>{when(order.report_ready_at, order.timezone)}</dd>
                    </div>
                    {order.has_prescription && (
                        <div>
                            <dt className="text-muted">Prescription</dt>
                            <dd>
                                <a
                                    href={route('orders.prescription', order.number)}
                                    className="inline-flex items-center gap-1.5 font-medium text-primary-700 hover:underline"
                                >
                                    <FileText className="size-4" aria-hidden="true" />
                                    {order.prescription_name ?? 'Download'}
                                </a>
                            </dd>
                        </div>
                    )}
                </dl>

                {!order.can_upload_reports && (
                    <p className="text-sm text-muted">
                        Reports can be uploaded once the sample is received at the lab.
                    </p>
                )}

                <ul className="space-y-3">
                    {patients.map(({ key, patient, items }) => (
                        <li key={key} className="rounded-card border border-border bg-surface">
                            <p className="border-b border-border px-4 py-3 text-sm">
                                <span className="font-medium">{patient.patient_name}</span>
                                <span className="text-muted">
                                    {' '}
                                    · born{' '}
                                    {formatDate(patient.patient_date_of_birth, {
                                        day: 'numeric',
                                        month: 'short',
                                        year: 'numeric',
                                    })}{' '}
                                    · {patient.patient_sex}
                                </span>
                            </p>
                            <ul className="divide-y divide-border">
                                {items.map((item) => (
                                    <li key={item.id} className="px-4 py-3">
                                        <div className="flex flex-wrap items-start justify-between gap-2">
                                            <div className="min-w-0">
                                                <p className="text-sm font-medium">
                                                    {item.name} <span className="text-muted">({item.code})</span>
                                                </p>
                                                {item.test_names && (
                                                    <p className="text-sm text-muted">{item.test_names.join(', ')}</p>
                                                )}
                                            </div>
                                            {item.report_name ? (
                                                <Badge tone="success">Report uploaded</Badge>
                                            ) : (
                                                <Badge>No report yet</Badge>
                                            )}
                                        </div>
                                        {item.report_name && (
                                            <a
                                                href={route('orders.reports.show', [order.number, item.id])}
                                                className="mt-1 inline-flex min-h-11 items-center gap-1.5 text-sm font-medium text-primary-700 hover:underline sm:min-h-0"
                                            >
                                                <FileText className="size-4" aria-hidden="true" />
                                                {item.report_name}
                                            </a>
                                        )}
                                        {order.can_upload_reports && <ReportUpload order={order} item={item} />}
                                    </li>
                                ))}
                            </ul>
                        </li>
                    ))}
                </ul>
            </div>
        </AdminLayout>
    );
}

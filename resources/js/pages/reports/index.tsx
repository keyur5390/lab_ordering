import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { TextLink } from '@/components/ui/text-link';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/format';
import { Head, router } from '@inertiajs/react';
import { FileText } from 'lucide-react';

interface ReportRow {
    id: number;
    patient_name: string;
    name: string;
    test_names: string[] | null;
    order_number: string;
    uploaded_at: string;
    url: string;
}

interface ReportsProps {
    patients: string[];
    patient: string | null;
    reports: ReportRow[];
}

export default function Reports({ patients, patient, reports }: ReportsProps) {
    // Grouped by patient, keeping newest-first order inside each group.
    const groups = reports.reduce<{ name: string; reports: ReportRow[] }[]>((all, report) => {
        const group = all.find((g) => g.name === report.patient_name);
        if (group) {
            group.reports.push(report);
        } else {
            all.push({ name: report.patient_name, reports: [report] });
        }

        return all;
    }, []);

    return (
        <AppLayout>
            <Head title="Reports" />

            <div className="mx-auto max-w-3xl px-4 py-8 sm:px-6 md:py-12 lg:px-8">
                <TextLink href={route('account')} className="text-sm">
                    ← Account
                </TextLink>
                <h1 className="mt-4 text-2xl font-semibold md:text-3xl">Reports</h1>
                <p className="mt-1 text-sm text-muted">Every report from your orders. We keep them for you.</p>

                {patients.length > 1 && (
                    <div className="mt-6 space-y-1.5">
                        <Label htmlFor="patient-filter">Patient</Label>
                        <Select
                            id="patient-filter"
                            className="w-full sm:w-64"
                            value={patient ?? ''}
                            onChange={(e) =>
                                router.get(route('reports.index'), e.target.value ? { patient: e.target.value } : {}, {
                                    preserveState: true,
                                    replace: true,
                                })
                            }
                        >
                            <option value="">Everyone</option>
                            {patients.map((name) => (
                                <option key={name} value={name}>
                                    {name}
                                </option>
                            ))}
                        </Select>
                    </div>
                )}

                {reports.length === 0 ? (
                    <div className="mt-6 flex flex-col items-center rounded-card border border-border bg-surface p-8 text-center md:p-12">
                        <FileText className="size-6 text-muted" strokeWidth={1.75} aria-hidden="true" />
                        <p className="mt-3 text-sm text-muted">
                            No reports yet. They appear here once the lab uploads them.
                        </p>
                    </div>
                ) : (
                    <div className="mt-6 space-y-6">
                        {groups.map((group) => (
                            <section key={group.name} aria-label={group.name}>
                                <h2 className="text-lg font-semibold">{group.name}</h2>
                                <ul className="mt-3 divide-y divide-border rounded-card border border-border bg-surface">
                                    {group.reports.map((report) => (
                                        <li
                                            key={report.id}
                                            className="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
                                        >
                                            <div className="min-w-0">
                                                <p className="font-medium">{report.name}</p>
                                                {report.test_names && (
                                                    <p className="text-sm text-muted">{report.test_names.join(', ')}</p>
                                                )}
                                                <p className="text-sm text-muted">
                                                    {formatDate(report.uploaded_at, {
                                                        day: 'numeric',
                                                        month: 'short',
                                                        year: 'numeric',
                                                    })}{' '}
                                                    ·{' '}
                                                    <TextLink href={route('orders.show', report.order_number)}>
                                                        {report.order_number}
                                                    </TextLink>
                                                </p>
                                            </div>
                                            <a
                                                href={report.url}
                                                className="inline-flex h-11 items-center gap-2 rounded-control border border-border bg-surface px-3 text-sm font-medium hover:bg-canvas"
                                            >
                                                <FileText className="size-4" aria-hidden="true" />
                                                Download
                                            </a>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

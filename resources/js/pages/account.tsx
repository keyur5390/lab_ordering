import AppLayout from '@/layouts/app-layout';
import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ChevronRight, Users } from 'lucide-react';

export default function Account() {
    const { auth } = usePage<SharedData>().props;

    return (
        <AppLayout>
            <Head title="Your account" />

            <section className="mx-auto max-w-7xl px-4 py-8 sm:px-6 md:py-12 lg:px-8">
                <h1 className="text-2xl font-semibold md:text-3xl">Your account</h1>

                <div className="mt-6 rounded-card border border-border bg-surface p-4 md:p-6">
                    <p className="text-sm text-muted">Signed in as</p>
                    <p className="mt-1 font-medium">{auth.user?.name}</p>
                    <p className="text-sm text-muted">{auth.user?.email}</p>
                </div>

                <Link
                    href={route('patients.index')}
                    className="mt-4 flex items-center gap-3 rounded-card border border-border bg-surface p-4 hover:bg-canvas md:p-6"
                >
                    <Users className="size-5 text-muted" strokeWidth={1.75} aria-hidden="true" />
                    <span className="flex-1">
                        <span className="block font-medium">Patients</span>
                        <span className="block text-sm text-muted">You and the family members you book tests for</span>
                    </span>
                    <ChevronRight className="size-5 text-muted" aria-hidden="true" />
                </Link>
            </section>
        </AppLayout>
    );
}

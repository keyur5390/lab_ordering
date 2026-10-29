import AppLayout from '@/layouts/app-layout';
import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ChevronRight, ClipboardList, MapPin, Users, type LucideIcon } from 'lucide-react';

export default function Account() {
    const { auth } = usePage<SharedData>().props;

    const links: { href: string; label: string; description: string; icon: LucideIcon }[] = [
        {
            href: route('orders.index'),
            label: 'Orders',
            description: 'Your bookings, collection times and changes',
            icon: ClipboardList,
        },
        {
            href: route('patients.index'),
            label: 'Patients',
            description: 'You and the family members you book tests for',
            icon: Users,
        },
        {
            href: route('addresses.index'),
            label: 'Addresses',
            description: 'Where we collect samples from you',
            icon: MapPin,
        },
    ];

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

                <ul className="mt-4 space-y-3">
                    {links.map(({ href, label, description, icon: Icon }) => (
                        <li key={href}>
                            <Link
                                href={href}
                                className="flex items-center gap-3 rounded-card border border-border bg-surface p-4 hover:bg-canvas md:p-6"
                            >
                                <Icon className="size-5 text-muted" strokeWidth={1.75} aria-hidden="true" />
                                <span className="flex-1">
                                    <span className="block font-medium">{label}</span>
                                    <span className="block text-sm text-muted">{description}</span>
                                </span>
                                <ChevronRight className="size-5 text-muted" aria-hidden="true" />
                            </Link>
                        </li>
                    ))}
                </ul>
            </section>
        </AppLayout>
    );
}

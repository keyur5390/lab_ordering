import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';

export default function Home() {
    return (
        <AppLayout>
            <Head title="Home" />

            <section className="mx-auto max-w-7xl px-4 py-12 sm:px-6 md:py-20 lg:px-8">
                {/* TODO(client): final homepage copy */}
                <h1 className="text-2xl font-semibold md:text-3xl">Book lab tests online</h1>
                <p className="mt-3 max-w-2xl text-base text-muted">
                    Browse tests, choose home collection or a lab visit, and get your reports online.
                </p>
                <Link
                    href={route('catalogue.index')}
                    className="mt-6 inline-flex h-11 items-center rounded-control bg-primary-600 px-6 text-sm font-medium text-white hover:bg-primary-700"
                >
                    Browse tests
                </Link>
            </section>
        </AppLayout>
    );
}

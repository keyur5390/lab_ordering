import { FaqList, type FaqItem } from '@/components/faq-list';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';

export default function Faq({ faqs }: { faqs: FaqItem[] }) {
    return (
        <AppLayout>
            <Head title="Frequently asked questions" />

            <section className="mx-auto max-w-3xl px-4 py-8 sm:px-6 md:py-12 lg:px-8">
                <h1 className="text-2xl font-semibold md:text-3xl">Frequently asked questions</h1>
                <div className="mt-6">
                    {faqs.length === 0 ? <p className="text-muted">No questions here yet.</p> : <FaqList faqs={faqs} />}
                </div>
            </section>
        </AppLayout>
    );
}

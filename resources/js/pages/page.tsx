import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/format';
import { Head } from '@inertiajs/react';

interface PageProps {
    page: { slug: string; title: string; html: string; updated_on: string | null };
    privacyContact: { name: string | null; email: string | null } | null;
}

export default function Page({ page, privacyContact }: PageProps) {
    return (
        <AppLayout>
            <Head title={page.title} />

            <article className="mx-auto max-w-3xl px-4 py-8 sm:px-6 md:py-12 lg:px-8">
                <h1 className="text-2xl font-semibold md:text-3xl">{page.title}</h1>
                {page.updated_on && (
                    <p className="mt-1 text-sm text-muted">
                        Last updated {formatDate(page.updated_on, { day: 'numeric', month: 'long', year: 'numeric' })}
                    </p>
                )}
                {/* Rendered from Markdown on the server, with raw HTML stripped. */}
                <div className="rich-text mt-6" dangerouslySetInnerHTML={{ __html: page.html }} />

                {privacyContact?.email && (
                    <section
                        aria-labelledby="contact-heading"
                        className="mt-8 rounded-card border border-border bg-surface p-4 md:p-6"
                    >
                        <h2 id="contact-heading" className="font-semibold">
                            Questions or complaints about your data
                        </h2>
                        <p className="mt-1 text-sm">
                            {privacyContact.name && <>{privacyContact.name} · </>}
                            <a
                                href={`mailto:${privacyContact.email}`}
                                className="text-primary-700 underline underline-offset-2"
                            >
                                {privacyContact.email}
                            </a>
                        </p>
                    </section>
                )}
            </article>
        </AppLayout>
    );
}

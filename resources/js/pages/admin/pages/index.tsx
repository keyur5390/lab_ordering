import { Alert } from '@/components/ui/alert';
import { TextLink } from '@/components/ui/text-link';
import AdminLayout from '@/layouts/admin-layout';
import type { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';

interface PagesProps {
    pages: { id: number; slug: string; title: string; url: string; updated_at: string | null }[];
}

export default function Pages({ pages }: PagesProps) {
    const { status } = usePage<SharedData>().props;

    return (
        <AdminLayout title="Pages">
            <p className="mb-4 max-w-3xl text-sm text-muted">
                The site's written pages. They're linked from the footer of every page, and the privacy policy and terms
                from the sign-up form.
            </p>
            {status && (
                <Alert tone="success" className="mb-4">
                    {status}
                </Alert>
            )}
            <ul className="divide-y divide-border rounded-card border border-border bg-surface">
                {pages.map((page) => (
                    <li key={page.id} className="flex items-center gap-3 p-4">
                        <div className="min-w-0 flex-1">
                            <Link href={route('admin.pages.edit', page.id)} className="font-medium hover:underline">
                                {page.title}
                            </Link>
                            <p className="truncate text-sm">
                                <TextLink href={page.url}>{new URL(page.url).pathname}</TextLink>
                            </p>
                        </div>
                        <Link
                            href={route('admin.pages.edit', page.id)}
                            aria-label={`Edit ${page.title}`}
                            className="flex size-11 items-center justify-center rounded-control hover:bg-canvas"
                        >
                            <ChevronRight className="size-5 text-muted" aria-hidden="true" />
                        </Link>
                    </li>
                ))}
            </ul>
        </AdminLayout>
    );
}

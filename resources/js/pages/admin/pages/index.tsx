import { TextLink } from '@/components/ui/text-link';
import AdminLayout from '@/layouts/admin-layout';
import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';

interface PagesProps {
    pages: { id: number; slug: string; title: string; url: string; updated_at: string | null }[];
}

export default function Pages({ pages }: PagesProps) {
    return (
        <AdminLayout title="Pages">
            <p className="mb-4 max-w-3xl text-sm text-muted">
                What customers read on the site. The pages are linked from the footer of every page, and the privacy
                policy and terms from the sign-up form.
            </p>
            <ul className="divide-y divide-border rounded-card border border-border bg-surface">
                {[
                    {
                        href: route('admin.pages.home.edit'),
                        title: 'Home page',
                        note: 'Heading, text and featured packages',
                    },
                    { href: route('admin.pages.faqs.index'), title: 'FAQs', note: 'On the home page and the FAQ page' },
                ].map((item) => (
                    <li key={item.href} className="flex items-center gap-3 p-4">
                        <div className="min-w-0 flex-1">
                            <Link href={item.href} className="font-medium hover:underline">
                                {item.title}
                            </Link>
                            <p className="text-sm text-muted">{item.note}</p>
                        </div>
                        <Link
                            href={item.href}
                            aria-label={`Edit ${item.title}`}
                            className="flex size-11 items-center justify-center rounded-control hover:bg-canvas"
                        >
                            <ChevronRight className="size-5 text-muted" aria-hidden="true" />
                        </Link>
                    </li>
                ))}
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

import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/react';
import { Ban, Clock, FileQuestion, ServerCrash, Wrench, type LucideIcon } from 'lucide-react';

type ErrorStatus = 403 | 404 | 419 | 429 | 500 | 503;

interface ErrorCopy {
    title: string;
    message: string;
    icon: LucideIcon;
    /** "retry" reloads the page; "home" links to the home page. */
    actions: ('retry' | 'home')[];
}

const copy: Record<ErrorStatus, ErrorCopy> = {
    403: {
        title: "You don't have access",
        message: "Your account isn't allowed to open this page.",
        icon: Ban,
        actions: ['home'],
    },
    404: {
        title: 'Page not found',
        message: "We couldn't find the page you were looking for. It may have moved or no longer exists.",
        icon: FileQuestion,
        actions: ['home'],
    },
    419: {
        title: 'Page expired',
        message: 'This page was open for too long. Reload it and try again.',
        icon: Clock,
        actions: ['retry'],
    },
    429: {
        title: 'Too many attempts',
        message: 'Please wait a moment, then try again.',
        icon: Clock,
        actions: ['retry'],
    },
    500: {
        title: 'Something went wrong',
        message: 'Sorry, something went wrong on our side. Please try again in a moment.',
        icon: ServerCrash,
        actions: ['retry', 'home'],
    },
    503: {
        title: 'Down for maintenance',
        message: "We're making some improvements. Please check back shortly.",
        icon: Wrench,
        actions: ['retry'],
    },
};

/**
 * Rendered by the exception handler in bootstrap/app.php. It receives its props directly
 * because shared props and the session may not exist when an error happens.
 */
export default function ErrorPage({ status, name }: { status: ErrorStatus; name: string }) {
    const { title, message, icon: Icon, actions } = copy[status] ?? copy[500];

    return (
        <div className="flex min-h-screen flex-col items-center justify-center px-4 py-10">
            <Head title={title} />

            <a href={route('home')} className="mb-6 text-xl font-semibold text-primary-700">
                {name}
            </a>

            <main className="w-full max-w-md rounded-card border border-border bg-surface p-6 text-center md:p-8">
                <Icon className="mx-auto size-6 text-muted" strokeWidth={1.75} aria-hidden="true" />
                <p className="mt-3 text-sm font-medium text-muted">Error {status}</p>
                <h1 className="mt-1 text-2xl font-semibold">{title}</h1>
                <p className="mt-2 text-base text-muted">{message}</p>

                <div className="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-center">
                    {actions.includes('retry') && <Button onClick={() => window.location.reload()}>Try again</Button>}
                    {actions.includes('home') && (
                        <Link
                            href={route('home')}
                            className={
                                actions.includes('retry')
                                    ? 'inline-flex h-11 items-center justify-center rounded-control border border-border bg-surface px-4 text-sm font-medium text-text hover:bg-canvas'
                                    : 'inline-flex h-11 items-center justify-center rounded-control bg-primary-600 px-4 text-sm font-medium text-white hover:bg-primary-700'
                            }
                        >
                            Go to home
                        </Link>
                    )}
                </div>
            </main>
        </div>
    );
}

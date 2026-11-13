import { Head } from '@inertiajs/react';

/** Page title plus the description and Open Graph tags search engines and link previews use (B-33). */
export function Seo({ title, description }: { title: string; description?: string | null }) {
    const summary = description ? description.replace(/\s+/g, ' ').trim().slice(0, 160) : null;

    return (
        <Head title={title}>
            {summary && <meta head-key="description" name="description" content={summary} />}
            <meta head-key="og:title" property="og:title" content={title} />
            {summary && <meta head-key="og:description" property="og:description" content={summary} />}
            <meta head-key="og:type" property="og:type" content="website" />
        </Head>
    );
}

export interface FaqItem {
    id: number;
    question: string;
    /** Rendered from Markdown on the server, with raw HTML stripped. */
    html: string;
}

/** Questions and answers as native disclosure widgets. */
export function FaqList({ faqs }: { faqs: FaqItem[] }) {
    return (
        <div className="divide-y divide-border rounded-card border border-border bg-surface">
            {faqs.map((faq) => (
                <details key={faq.id} className="group p-4 md:px-6">
                    <summary className="flex min-h-11 cursor-pointer list-none items-center justify-between gap-4 font-medium">
                        {faq.question}
                        <span
                            aria-hidden="true"
                            className="text-xl text-muted transition-transform group-open:rotate-45"
                        >
                            +
                        </span>
                    </summary>
                    <div className="rich-text mt-2 text-sm text-muted" dangerouslySetInnerHTML={{ __html: faq.html }} />
                </details>
            ))}
        </div>
    );
}

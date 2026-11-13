import { DeleteButton } from '@/components/delete-button';
import { Field } from '@/components/field';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { TextLink } from '@/components/ui/text-link';
import AdminLayout from '@/layouts/admin-layout';
import { router, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, CircleHelp } from 'lucide-react';
import type { FormEvent } from 'react';

interface FaqRow {
    id: number;
    question: string;
    answer: string;
}

const answerHint = 'Markdown: a blank line starts a paragraph, - a list item, **bold**, [link](https://…).';

function FaqItem({ faq, first, last }: { faq: FaqRow; first: boolean; last: boolean }) {
    const { data, setData, put, processing, errors, isDirty } = useForm({ question: faq.question, answer: faq.answer });
    const move = (direction: 'up' | 'down') =>
        router.post(route('admin.pages.faqs.move', faq.id), { direction }, { preserveScroll: true });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put(route('admin.pages.faqs.update', faq.id), { preserveScroll: true });
    };

    return (
        <li className="p-4 md:p-6">
            <form onSubmit={submit} noValidate className="space-y-3">
                <FormField
                    id={`question-${faq.id}`}
                    label="Question"
                    value={data.question}
                    onChange={(e) => setData('question', e.target.value)}
                    error={errors.question}
                />
                <Field id={`answer-${faq.id}`} label="Answer" hint={answerHint} error={errors.answer}>
                    {(control) => (
                        <Textarea
                            {...control}
                            rows={3}
                            value={data.answer}
                            onChange={(e) => setData('answer', e.target.value)}
                        />
                    )}
                </Field>
                <div className="flex flex-wrap items-center gap-2">
                    <Button type="submit" variant="secondary" processing={processing} disabled={!isDirty}>
                        Save
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        className="w-11 px-0"
                        aria-label={`Move "${faq.question}" up`}
                        disabled={first}
                        onClick={() => move('up')}
                    >
                        <ArrowUp className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        className="w-11 px-0"
                        aria-label={`Move "${faq.question}" down`}
                        disabled={last}
                        onClick={() => move('down')}
                    >
                        <ArrowDown className="size-4" />
                    </Button>
                    <span className="flex-1" />
                    <DeleteButton
                        href={route('admin.pages.faqs.destroy', faq.id)}
                        itemName={faq.question}
                        itemType="question"
                    />
                </div>
            </form>
        </li>
    );
}

export default function Faqs({ faqs }: { faqs: FaqRow[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({ question: '', answer: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('admin.pages.faqs.store'), { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <AdminLayout title="FAQs">
            <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                <TextLink href={route('admin.pages.index')}>← Pages</TextLink>
                <TextLink href={route('faq')}>View FAQ page</TextLink>
            </div>
            <p className="mt-4 mb-4 max-w-3xl text-sm text-muted">
                Shown on the home page and the FAQ page, in this order.
            </p>

            {faqs.length === 0 ? (
                <div className="mb-6 flex max-w-3xl flex-col items-center rounded-card border border-border bg-surface p-8 text-center">
                    <CircleHelp className="size-6 text-muted" strokeWidth={1.75} aria-hidden="true" />
                    <p className="mt-3 text-sm text-muted">No questions yet.</p>
                </div>
            ) : (
                <ul className="mb-6 max-w-3xl divide-y divide-border rounded-card border border-border bg-surface">
                    {faqs.map((faq, index) => (
                        <FaqItem
                            key={`${faq.id}-${faq.question}-${faq.answer}`}
                            faq={faq}
                            first={index === 0}
                            last={index === faqs.length - 1}
                        />
                    ))}
                </ul>
            )}

            <form
                onSubmit={submit}
                noValidate
                className="max-w-3xl space-y-3 rounded-card border border-border bg-surface p-4 md:p-6"
            >
                <h2 className="text-base font-semibold">Add a question</h2>
                <FormField
                    id="new-question"
                    label="Question"
                    value={data.question}
                    onChange={(e) => setData('question', e.target.value)}
                    error={errors.question}
                />
                <Field id="new-answer" label="Answer" hint={answerHint} error={errors.answer}>
                    {(control) => (
                        <Textarea
                            {...control}
                            rows={3}
                            value={data.answer}
                            onChange={(e) => setData('answer', e.target.value)}
                        />
                    )}
                </Field>
                <Button type="submit" processing={processing}>
                    Add question
                </Button>
            </form>
        </AdminLayout>
    );
}

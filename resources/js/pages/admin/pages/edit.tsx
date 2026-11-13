import { Field } from '@/components/field';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { TextLink } from '@/components/ui/text-link';
import AdminLayout from '@/layouts/admin-layout';
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface EditProps {
    page: { id: number; title: string; body: string; url: string };
}

export default function PageEdit({ page }: EditProps) {
    const { data, setData, put, processing, errors, isDirty } = useForm({ title: page.title, body: page.body });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put(route('admin.pages.update', page.id));
    };

    return (
        <AdminLayout title={page.title}>
            <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                <TextLink href={route('admin.pages.index')}>← Pages</TextLink>
                <TextLink href={page.url}>View page</TextLink>
            </div>

            <form
                onSubmit={submit}
                noValidate
                className="mt-4 max-w-3xl space-y-4 rounded-card border border-border bg-surface p-4 md:p-6"
            >
                <FormField
                    id="title"
                    label="Title"
                    value={data.title}
                    onChange={(e) => setData('title', e.target.value)}
                    error={errors.title}
                />
                <Field
                    id="body"
                    label="Text"
                    hint="Markdown: a blank line starts a paragraph, ## a heading, - a list item, **bold**, [link](https://…)."
                    error={errors.body}
                >
                    {(control) => (
                        <Textarea
                            {...control}
                            rows={20}
                            className="font-mono text-sm"
                            value={data.body}
                            onChange={(e) => setData('body', e.target.value)}
                        />
                    )}
                </Field>
                <Button type="submit" processing={processing} disabled={!isDirty}>
                    Save page
                </Button>
            </form>
        </AdminLayout>
    );
}

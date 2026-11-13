import { Field } from '@/components/field';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { InputError } from '@/components/ui/input-error';
import { Textarea } from '@/components/ui/textarea';
import { TextLink } from '@/components/ui/text-link';
import AdminLayout from '@/layouts/admin-layout';
import { useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp } from 'lucide-react';
import type { FormEvent } from 'react';

interface HomeContentProps {
    home: { hero_title: string; hero_text: string | null; hero_button: string; featured_package_ids: number[] };
    packages: { id: number; name: string; is_active: boolean }[];
}

export default function HomeContent({ home, packages }: HomeContentProps) {
    const { data, setData, put, processing, errors, isDirty } = useForm({
        hero_title: home.hero_title,
        hero_text: home.hero_text ?? '',
        hero_button: home.hero_button,
        featured_package_ids: home.featured_package_ids,
    });
    const byId = new Map(packages.map((pkg) => [pkg.id, pkg]));
    const featured = data.featured_package_ids.filter((id) => byId.has(id));

    const toggle = (id: number, on: boolean) =>
        setData('featured_package_ids', on ? [...featured, id] : featured.filter((other) => other !== id));
    const move = (index: number, by: -1 | 1) => {
        const next = [...featured];
        const [moved] = next.splice(index, 1);
        if (moved === undefined) return;
        next.splice(index + by, 0, moved);
        setData('featured_package_ids', next);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put(route('admin.pages.home.update'), { preserveScroll: true });
    };

    return (
        <AdminLayout title="Home page">
            <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                <TextLink href={route('admin.pages.index')}>← Pages</TextLink>
                <TextLink href={route('home')}>View home page</TextLink>
            </div>

            <form onSubmit={submit} noValidate className="mt-4 max-w-3xl space-y-6">
                <section className="space-y-4 rounded-card border border-border bg-surface p-4 md:p-6">
                    <h2 className="text-base font-semibold">Top of the page</h2>
                    <FormField
                        id="hero_title"
                        label="Heading"
                        value={data.hero_title}
                        onChange={(e) => setData('hero_title', e.target.value)}
                        error={errors.hero_title}
                    />
                    <Field id="hero_text" label="Text under the heading (optional)" error={errors.hero_text}>
                        {(control) => (
                            <Textarea
                                {...control}
                                rows={3}
                                value={data.hero_text}
                                onChange={(e) => setData('hero_text', e.target.value)}
                            />
                        )}
                    </Field>
                    <FormField
                        id="hero_button"
                        label="Button"
                        hint="Opens the catalogue."
                        value={data.hero_button}
                        onChange={(e) => setData('hero_button', e.target.value)}
                        error={errors.hero_button}
                    />
                </section>

                <section className="space-y-4 rounded-card border border-border bg-surface p-4 md:p-6">
                    <div>
                        <h2 className="text-base font-semibold">Featured packages</h2>
                        <p className="mt-1 text-sm text-muted">
                            Shown under the heading, in this order. Unpublished packages are hidden until they're
                            published again.
                        </p>
                    </div>

                    {featured.length > 0 && (
                        <ol className="divide-y divide-border rounded-control border border-border">
                            {featured.map((id, index) => (
                                <li key={id} className="flex items-center gap-2 px-3 py-1">
                                    <span className="w-6 text-sm text-muted">{index + 1}.</span>
                                    <span className="min-w-0 flex-1 truncate text-sm">
                                        {byId.get(id)!.name}
                                        {!byId.get(id)!.is_active && <span className="text-muted"> (unpublished)</span>}
                                    </span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        className="w-11 px-0"
                                        aria-label={`Move ${byId.get(id)!.name} up`}
                                        disabled={index === 0}
                                        onClick={() => move(index, -1)}
                                    >
                                        <ArrowUp className="size-4" />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        className="w-11 px-0"
                                        aria-label={`Move ${byId.get(id)!.name} down`}
                                        disabled={index === featured.length - 1}
                                        onClick={() => move(index, 1)}
                                    >
                                        <ArrowDown className="size-4" />
                                    </Button>
                                </li>
                            ))}
                        </ol>
                    )}

                    {packages.length === 0 ? (
                        <p className="text-sm text-muted">
                            No packages yet. <TextLink href={route('admin.packages.create')}>Add a package</TextLink>
                        </p>
                    ) : (
                        <fieldset>
                            <legend className="text-sm font-medium">Choose packages</legend>
                            <div className="mt-1 grid sm:grid-cols-2">
                                {packages.map((pkg) => (
                                    <label key={pkg.id} className="flex min-h-11 items-center gap-3 text-sm">
                                        <Checkbox
                                            checked={featured.includes(pkg.id)}
                                            onChange={(e) => toggle(pkg.id, e.target.checked)}
                                        />
                                        <span>
                                            {pkg.name}
                                            {!pkg.is_active && <span className="text-muted"> (unpublished)</span>}
                                        </span>
                                    </label>
                                ))}
                            </div>
                        </fieldset>
                    )}
                    <InputError message={errors.featured_package_ids} />
                </section>

                <Button type="submit" processing={processing} disabled={!isDirty}>
                    Save home page
                </Button>
            </form>
        </AdminLayout>
    );
}

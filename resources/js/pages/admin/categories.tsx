import { DeleteButton } from '@/components/delete-button';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { InputError } from '@/components/ui/input-error';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';
import { useForm } from '@inertiajs/react';
import { Tags } from 'lucide-react';
import type { FormEvent } from 'react';

interface CategoryRow {
    id: number;
    name: string;
    slug: string;
    tests_count: number;
    packages_count: number;
}

function CategoryItem({ category }: { category: CategoryRow }) {
    const { data, setData, put, processing, errors, isDirty } = useForm({ name: category.name });
    const id = `category-${category.id}`;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put(route('admin.categories.update', category.slug), { preserveScroll: true });
    };

    return (
        <li className="flex flex-wrap items-start justify-between gap-3 p-4">
            <form onSubmit={submit} className="flex min-w-0 flex-1 flex-wrap items-start gap-2">
                <div className="min-w-48 flex-1">
                    <Label htmlFor={id} className="sr-only">
                        Name
                    </Label>
                    <Input id={id} value={data.name} onChange={(e) => setData('name', e.target.value)} />
                    <InputError message={errors.name} className="mt-1" />
                    <p className="mt-1 text-sm text-muted">
                        {category.tests_count} {category.tests_count === 1 ? 'test' : 'tests'} ·{' '}
                        {category.packages_count} {category.packages_count === 1 ? 'package' : 'packages'}
                    </p>
                </div>
                {isDirty && (
                    <Button type="submit" variant="secondary" processing={processing}>
                        Rename
                    </Button>
                )}
            </form>
            <DeleteButton
                href={route('admin.categories.destroy', category.slug)}
                itemName={category.name}
                itemType="category"
                description="Its tests and packages stay; they just leave this category."
            />
        </li>
    );
}

export default function Categories({ categories }: { categories: CategoryRow[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({ name: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('admin.categories.store'), { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <AdminLayout title="Categories">
            <p className="mb-4 max-w-3xl text-sm text-muted">
                Customers browse the catalogue by category. Put tests and packages in categories from their own forms;
                one item can be in several.
            </p>

            <form
                onSubmit={submit}
                noValidate
                className="mb-6 flex max-w-3xl flex-wrap items-end gap-2 rounded-card border border-border bg-surface p-4"
            >
                <div className="min-w-48 flex-1 space-y-1.5">
                    <Label htmlFor="new-category">New category</Label>
                    <Input
                        id="new-category"
                        placeholder="e.g. Diabetes"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                    />
                    <InputError message={errors.name} />
                </div>
                <Button type="submit" processing={processing}>
                    Add
                </Button>
            </form>

            {categories.length === 0 ? (
                <div className="flex max-w-3xl flex-col items-center rounded-card border border-border bg-surface p-8 text-center">
                    <Tags className="size-6 text-muted" strokeWidth={1.75} aria-hidden="true" />
                    <p className="mt-3 text-sm text-muted">No categories yet.</p>
                </div>
            ) : (
                <ul className="max-w-3xl divide-y divide-border rounded-card border border-border bg-surface">
                    {categories.map((category) => (
                        <CategoryItem key={`${category.id}-${category.name}`} category={category} />
                    ))}
                </ul>
            )}
        </AdminLayout>
    );
}

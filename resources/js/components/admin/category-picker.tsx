import { Checkbox } from '@/components/ui/checkbox';
import { InputError } from '@/components/ui/input-error';
import { TextLink } from '@/components/ui/text-link';

interface CategoryPickerProps {
    categories: { id: number; name: string }[];
    value: number[];
    onChange: (ids: number[]) => void;
    error?: string;
}

/** Categories for a test or package (B-19): any number. */
export function CategoryPicker({ categories, value, onChange, error }: CategoryPickerProps) {
    return (
        <fieldset className="space-y-2">
            <legend className="text-sm font-medium">Categories</legend>
            {categories.length === 0 ? (
                <p className="text-sm text-muted">
                    No categories yet. <TextLink href={route('admin.categories.index')}>Add categories</TextLink>
                </p>
            ) : (
                <div className="flex flex-wrap gap-2">
                    {categories.map((category) => (
                        <label
                            key={category.id}
                            className="flex min-h-11 cursor-pointer items-center gap-2 rounded-control border border-border px-3 text-sm has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50"
                        >
                            <Checkbox
                                checked={value.includes(category.id)}
                                onChange={(e) =>
                                    onChange(
                                        e.target.checked
                                            ? [...value, category.id]
                                            : value.filter((id) => id !== category.id),
                                    )
                                }
                            />
                            {category.name}
                        </label>
                    ))}
                </div>
            )}
            <InputError message={error} />
        </fieldset>
    );
}

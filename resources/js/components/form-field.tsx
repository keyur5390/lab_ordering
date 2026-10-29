import { Input, type InputProps } from '@/components/ui/input';
import { InputError } from '@/components/ui/input-error';
import { Label } from '@/components/ui/label';
import type { ReactNode } from 'react';

interface FormFieldProps extends Omit<InputProps, 'id' | 'invalid'> {
    id: string;
    label: string;
    error?: string;
    /** Rendered next to the label, e.g. a "Forgot password?" link. */
    labelAside?: ReactNode;
}

/** Labelled input with its validation error linked via aria-describedby (DESIGN.md §5). */
export function FormField({ id, label, error, labelAside, ...props }: FormFieldProps) {
    const errorId = `${id}-error`;

    return (
        <div className="space-y-1.5">
            <div className="flex items-center justify-between gap-2">
                <Label htmlFor={id}>{label}</Label>
                {labelAside}
            </div>
            <Input id={id} invalid={!!error} aria-describedby={error ? errorId : undefined} {...props} />
            <InputError id={errorId} message={error} />
        </div>
    );
}

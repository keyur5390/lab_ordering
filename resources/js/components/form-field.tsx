import { Field } from '@/components/field';
import { Input, type InputProps } from '@/components/ui/input';
import type { ReactNode } from 'react';

interface FormFieldProps extends Omit<InputProps, 'id' | 'invalid'> {
    id: string;
    label: string;
    error?: string;
    hint?: string;
    /** Rendered next to the label, e.g. a "Forgot password?" link. */
    labelAside?: ReactNode;
}

/** Labelled text input (see Field for selects, textareas and other controls). */
export function FormField({ id, label, error, hint, labelAside, ...props }: FormFieldProps) {
    return (
        <Field id={id} label={label} error={error} hint={hint} labelAside={labelAside}>
            {(control) => <Input {...control} {...props} />}
        </Field>
    );
}

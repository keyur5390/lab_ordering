import { cn } from '@/lib/utils';
import type { InputHTMLAttributes } from 'react';

export interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
    invalid?: boolean;
}

export function Input({ className, invalid = false, type = 'text', ...props }: InputProps) {
    return (
        <input
            type={type}
            aria-invalid={invalid || undefined}
            className={cn(
                'block h-11 w-full rounded-control border border-border bg-surface px-3 text-base text-text sm:text-sm',
                'placeholder:text-muted',
                'focus-visible:border-primary-600 focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-primary-100',
                'disabled:cursor-not-allowed disabled:opacity-50',
                invalid && 'border-danger-600',
                className,
            )}
            {...props}
        />
    );
}

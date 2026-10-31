import { cn } from '@/lib/utils';
import { LoaderCircle } from 'lucide-react';
import type { ButtonHTMLAttributes } from 'react';

const variants = {
    primary: 'bg-primary-600 text-white hover:bg-primary-700',
    secondary: 'border border-border bg-surface text-text hover:bg-canvas',
    ghost: 'text-text hover:bg-canvas',
    danger: 'bg-danger-600 text-white hover:opacity-90',
} as const;

const sizes = {
    // 44px touch target on mobile (DESIGN.md §5); compact height from the sm breakpoint up.
    sm: 'h-11 px-3 text-sm sm:h-9',
    md: 'h-11 px-4 text-sm',
    lg: 'h-12 px-6 text-base',
} as const;

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: keyof typeof variants;
    size?: keyof typeof sizes;
    processing?: boolean;
}

export function Button({
    variant = 'primary',
    size = 'md',
    processing = false,
    disabled,
    className,
    children,
    type = 'button',
    ...props
}: ButtonProps) {
    return (
        <button
            type={type}
            disabled={disabled || processing}
            aria-busy={processing || undefined}
            className={cn(
                'inline-flex items-center justify-center gap-2 rounded-control font-medium whitespace-nowrap transition-colors',
                'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600',
                'disabled:pointer-events-none disabled:opacity-50',
                variants[variant],
                sizes[size],
                className,
            )}
            {...props}
        >
            {processing && <LoaderCircle className="size-4 animate-spin" aria-hidden="true" />}
            {children}
        </button>
    );
}

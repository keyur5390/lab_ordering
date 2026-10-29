import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'react';

const tones = {
    neutral: 'bg-canvas text-muted border-border',
    info: 'bg-primary-50 text-primary-700 border-primary-100',
    success: 'bg-surface text-success-600 border-success-600/30',
    warning: 'bg-surface text-warning-600 border-warning-600/30',
    danger: 'bg-surface text-danger-600 border-danger-600/30',
} as const;

export interface BadgeProps extends HTMLAttributes<HTMLSpanElement> {
    tone?: keyof typeof tones;
}

export function Badge({ tone = 'neutral', className, ...props }: BadgeProps) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium',
                tones[tone],
                className,
            )}
            {...props}
        />
    );
}

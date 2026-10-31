import { cn } from '@/lib/utils';
import type { ReactNode } from 'react';

const tones = {
    success: 'border-success-600/30 text-success-600',
    warning: 'border-warning-600/30 text-warning-600',
    danger: 'border-danger-600/30 text-danger-600',
} as const;

export function Alert({
    tone,
    children,
    className,
}: {
    tone: keyof typeof tones;
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            role={tone === 'success' ? 'status' : 'alert'}
            className={cn('rounded-control border bg-surface p-3 text-sm', tones[tone], className)}
        >
            {children}
        </div>
    );
}

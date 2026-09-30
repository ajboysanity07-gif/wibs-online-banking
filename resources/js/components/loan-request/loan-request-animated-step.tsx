import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    show: boolean;
    direction: 'forward' | 'backward';
    children: ReactNode;
};

// Mounts on `show`, so the entrance animation replays on every step change.
// The keyframes (and their prefers-reduced-motion gate) live in app.css.
export function LoanRequestAnimatedStep({ show, direction, children }: Props) {
    if (!show) {
        return null;
    }

    return (
        <div
            className={cn(
                'space-y-6',
                direction === 'forward'
                    ? 'animate-step-forward'
                    : 'animate-step-back',
            )}
        >
            {children}
        </div>
    );
}

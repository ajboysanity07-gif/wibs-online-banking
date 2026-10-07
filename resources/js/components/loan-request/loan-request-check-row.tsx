import type { ReactNode } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type Props = {
    id: string;
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
    children: ReactNode;
    invalid?: boolean;
    ariaLabel?: string;
};

/** Tappable card-style confirmation row with a 24px checkbox. */
export function LoanRequestCheckRow({
    id,
    checked,
    onCheckedChange,
    children,
    invalid,
    ariaLabel,
}: Props) {
    return (
        <Label
            htmlFor={id}
            className={cn(
                'flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border-[1.5px] border-border bg-card p-4 text-sm leading-snug transition-colors hover:bg-muted has-data-[state=checked]:border-primary has-data-[state=checked]:bg-card',
                invalid && 'border-destructive',
            )}
        >
            <Checkbox
                id={id}
                checked={checked}
                aria-label={ariaLabel}
                aria-invalid={invalid}
                onCheckedChange={(value) => onCheckedChange(value === true)}
                className="size-6 rounded-md [&_svg]:size-4"
            />
            <span className="min-w-0 flex-1">{children}</span>
        </Label>
    );
}

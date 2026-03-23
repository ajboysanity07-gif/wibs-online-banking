import type { VariantProps } from 'class-variance-authority';
import type { badgeVariants } from '@/components/ui/badge';
import type { MemberSavingsLedgerEntry } from '@/types/admin';

export type SavingsMovement = 'Deposit' | 'Withdrawal' | 'Unknown';

type BadgeVariant = VariantProps<typeof badgeVariants>['variant'];

const movementVariants: Record<SavingsMovement, BadgeVariant> = {
    Deposit: 'secondary',
    Withdrawal: 'outline',
    Unknown: 'outline',
};

export const resolveSavingsMovement = (
    entry: MemberSavingsLedgerEntry,
): SavingsMovement => {
    const deposit = entry.deposit ?? 0;
    const withdrawal = entry.withdrawal ?? 0;

    if (deposit > 0) {
        return 'Deposit';
    }

    if (withdrawal > 0) {
        return 'Withdrawal';
    }

    return 'Unknown';
};

export const getSavingsMovementMeta = (
    entry: MemberSavingsLedgerEntry,
): {
    movement: SavingsMovement;
    label: string;
    variant: BadgeVariant;
} => {
    const movement = resolveSavingsMovement(entry);

    return {
        movement,
        label: movement === 'Unknown' ? '--' : movement,
        variant: movementVariants[movement],
    };
};

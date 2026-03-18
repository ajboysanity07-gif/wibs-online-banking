import type { MemberStatusValue } from '@/types/admin';

type MemberStatusInput = MemberStatusValue | string | null | undefined;
type MemberStatusVariant = 'default' | 'secondary' | 'destructive' | 'outline';

export const getMemberStatusVariant = (
    status?: MemberStatusInput,
): MemberStatusVariant => {
    if (status === 'active') {
        return 'default';
    }

    if (status === 'pending') {
        return 'secondary';
    }

    if (status === 'suspended') {
        return 'destructive';
    }

    return 'outline';
};

export const getMemberStatusLabel = (status?: MemberStatusInput): string => {
    if (status === 'active') {
        return 'Active';
    }

    if (status === 'pending') {
        return 'Pending';
    }

    if (status === 'suspended') {
        return 'Suspended';
    }

    return 'Unknown';
};

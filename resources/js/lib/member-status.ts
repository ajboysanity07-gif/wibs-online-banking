import type { MemberRegistrationStatus, MemberStatusValue } from '@/types/admin';

type MemberStatusInput = MemberStatusValue | string | null | undefined;
type MemberStatusVariant = 'default' | 'secondary' | 'destructive' | 'outline';

export const getMemberStatusVariant = (
    status?: MemberStatusInput,
): MemberStatusVariant => {
    if (status === 'active') {
        return 'default';
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

    if (status === 'suspended') {
        return 'Suspended';
    }

    return 'Unknown';
};

type MemberRegistrationInput =
    | MemberRegistrationStatus
    | string
    | null
    | undefined;

export const getRegistrationStatusVariant = (
    status?: MemberRegistrationInput,
): MemberStatusVariant => {
    if (status === 'registered') {
        return 'default';
    }

    if (status === 'unregistered') {
        return 'secondary';
    }

    return 'outline';
};

export const getRegistrationStatusLabel = (
    status?: MemberRegistrationInput,
): string => {
    if (status === 'registered') {
        return 'Registered';
    }

    if (status === 'unregistered') {
        return 'Unregistered';
    }

    return 'Unknown';
};

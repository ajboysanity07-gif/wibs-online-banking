import { useCallback } from 'react';

export type GetInitialsFn = (fullName?: string | null) => string;

export function useInitials(): GetInitialsFn {
    return useCallback((fullName?: string | null): string => {
        const safe = (fullName ?? '').trim();
        if (!safe) {
            return '';
        }

        const names = safe.split(/\s+/);

        if (names.length === 1) {
            return names[0].charAt(0).toUpperCase();
        }

        const firstInitial = names[0].charAt(0);
        const lastInitial = names[names.length - 1].charAt(0);

        return `${firstInitial}${lastInitial}`.toUpperCase();
    }, []);
}

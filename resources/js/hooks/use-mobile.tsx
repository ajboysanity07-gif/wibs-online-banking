import { useCallback, useSyncExternalStore } from 'react';

const DEFAULT_MOBILE_BREAKPOINT = 768;

export function useIsMobile(breakpoint = DEFAULT_MOBILE_BREAKPOINT): boolean {
    const query = `(max-width: ${breakpoint - 1}px)`;

    const subscribe = useCallback(
        (callback: () => void) => {
            if (typeof window === 'undefined') {
                return () => {};
            }

            const mql = window.matchMedia(query);

            mql.addEventListener('change', callback);

            return () => mql.removeEventListener('change', callback);
        },
        [query],
    );

    return useSyncExternalStore(
        subscribe,
        () => window.matchMedia(query).matches,
        () => false,
    );
}

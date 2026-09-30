import { useState } from 'react';

const STORAGE_KEY = 'wibs:hide-balances';

// Per-viewer display preference only; storage can be blocked or throw.
export function useHideBalances() {
    const [hidden, setHidden] = useState(() => {
        try {
            return window.localStorage.getItem(STORAGE_KEY) === '1';
        } catch {
            return false;
        }
    });

    const toggle = () => {
        const next = !hidden;

        setHidden(next);

        try {
            window.localStorage.setItem(STORAGE_KEY, next ? '1' : '0');
        } catch {
            // Preference just won't persist.
        }
    };

    return [hidden, toggle] as const;
}

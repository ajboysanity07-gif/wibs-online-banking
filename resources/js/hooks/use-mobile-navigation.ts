export type CleanupFn = () => void;

export function useMobileNavigation(): CleanupFn {
    return () => {
        // Remove pointer-events style from body...
        document.body.style.removeProperty('pointer-events');
    };
}

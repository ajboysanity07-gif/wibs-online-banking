const RECENT_AUDIT_WINDOW_MS = 24 * 60 * 60 * 1000;

/** An audit entry is "new" when logged within the last 24 hours. */
export const isRecentAuditEntry = (
    createdAt: string | null,
    now: number = Date.now(),
): boolean => {
    const logged = createdAt ? new Date(createdAt).getTime() : NaN;

    return (
        !Number.isNaN(logged) &&
        now - logged >= 0 &&
        now - logged < RECENT_AUDIT_WINDOW_MS
    );
};

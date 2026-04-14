import { Bell, CheckCheck } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import { notificationsApi } from '@/lib/api/notifications';
import { formatDateTime } from '@/lib/formatters';
import { cn } from '@/lib/utils';
import type { NotificationItem } from '@/types/notifications';

const MAX_BADGE_COUNT = 99;

const formatBadgeCount = (count: number): string =>
    count > MAX_BADGE_COUNT ? `${MAX_BADGE_COUNT}+` : `${count}`;

export function NotificationBell() {
    const [open, setOpen] = useState(false);
    const [notifications, setNotifications] = useState<NotificationItem[]>([]);
    const [unreadCount, setUnreadCount] = useState(0);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const loadUnreadCount = useCallback(async () => {
        try {
            const count = await notificationsApi.getUnreadCount();
            setUnreadCount(count);
        } catch {}
    }, []);

    const loadNotifications = useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            const items = await notificationsApi.getNotifications();
            setNotifications(items);
        } catch {
            setError('Unable to load notifications right now.');
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        void loadUnreadCount();
    }, [loadUnreadCount]);

    const hasUnread = unreadCount > 0;

    const handleOpenChange = (nextOpen: boolean) => {
        setOpen(nextOpen);

        if (nextOpen) {
            void loadNotifications();
            void loadUnreadCount();
        }
    };

    const handleNotificationSelect = useCallback(
        async (notification: NotificationItem) => {
            if (notification.read_at) {
                return;
            }

            try {
                const result = await notificationsApi.markAsRead(
                    notification.id,
                );
                setNotifications((current) =>
                    current.map((item) =>
                        item.id === result.notification.id
                            ? result.notification
                            : item,
                    ),
                );
                setUnreadCount(result.unreadCount);
            } catch {}
        },
        [],
    );

    const handleMarkAllAsRead = useCallback(async () => {
        if (!hasUnread) {
            return;
        }

        try {
            const result = await notificationsApi.markAllAsRead();
            setNotifications((current) =>
                current.map((item) => ({
                    ...item,
                    read_at: result.readAt,
                })),
            );
            setUnreadCount(result.unreadCount);
        } catch {}
    }, [hasUnread]);

    const emptyState = useMemo(() => {
        if (loading) {
            return 'Loading notifications...';
        }

        if (error) {
            return error;
        }

        return 'No notifications yet.';
    }, [error, loading]);

    return (
        <DropdownMenu open={open} onOpenChange={handleOpenChange}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative"
                    aria-label="Notifications"
                >
                    <Bell className="h-5 w-5" />
                    {hasUnread && (
                        <span className="absolute -right-1 -top-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-semibold text-white">
                            {formatBadgeCount(unreadCount)}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="w-80 p-0 sm:w-96"
            >
                <div className="flex items-center justify-between border-b border-border px-3 py-2">
                    <span className="text-sm font-semibold">Notifications</span>
                    {hasUnread ? (
                        <button
                            type="button"
                            onClick={() => void handleMarkAllAsRead()}
                            className="flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground"
                        >
                            <CheckCheck className="h-3 w-3" />
                            Mark all as read
                        </button>
                    ) : null}
                </div>
                <div className="max-h-96 overflow-y-auto">
                    {loading || error || notifications.length === 0 ? (
                        <div className="px-3 py-6 text-sm text-muted-foreground">
                            {emptyState}
                        </div>
                    ) : (
                        notifications.map((notification) => {
                            const data = notification.data;
                            const createdAt = notification.created_at
                                ? formatDateTime(notification.created_at)
                                : null;
                            const notes = data.decision_notes?.trim();

                            return (
                                <DropdownMenuItem
                                    key={notification.id}
                                    onSelect={() =>
                                        void handleNotificationSelect(
                                            notification,
                                        )
                                    }
                                    className="cursor-pointer items-start gap-3 px-3 py-2"
                                >
                                    <span
                                        className={cn(
                                            'mt-2 size-2 rounded-full',
                                            notification.read_at
                                                ? 'bg-muted-foreground/30'
                                                : 'bg-primary',
                                        )}
                                    />
                                    <div className="flex min-w-0 flex-1 flex-col gap-1">
                                        <div className="flex items-center justify-between gap-3">
                                            <span
                                                className={cn(
                                                    'text-sm',
                                                    notification.read_at
                                                        ? 'font-normal'
                                                        : 'font-semibold',
                                                )}
                                            >
                                                {data.title}
                                            </span>
                                            {createdAt ? (
                                                <span className="shrink-0 text-[11px] text-muted-foreground">
                                                    {createdAt}
                                                </span>
                                            ) : null}
                                        </div>
                                        <span className="text-xs text-muted-foreground">
                                            {data.message}
                                        </span>
                                        {notes ? (
                                            <span className="text-xs text-muted-foreground">
                                                {notes}
                                            </span>
                                        ) : null}
                                    </div>
                                </DropdownMenuItem>
                            );
                        })
                    )}
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

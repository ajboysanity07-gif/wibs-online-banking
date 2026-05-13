import { Link, router } from '@inertiajs/react';
import { ArrowRight, Bell, BellDot, CheckCheck, Clock3, Inbox, TriangleAlert } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { useCallback, useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Skeleton } from '@/components/ui/skeleton';
import { notificationsApi } from '@/lib/api/notifications';
import {
    buildNotificationMetadataChips,
    chipClassNames,
    formatNotificationBadgeCount,
    formatNotificationTimestamp,
    getNotificationVisual,
    resolveNotificationDestination,
    type NotificationChip,
} from '@/lib/notification-ui';
import { cn } from '@/lib/utils';
import type { NotificationItem } from '@/types/notifications';

function NotificationHeader({
    hasUnread,
    loading,
    unreadCount,
    onMarkAllAsRead,
}: {
    hasUnread: boolean;
    loading: boolean;
    unreadCount: number;
    onMarkAllAsRead: () => void;
}) {
    return (
        <div className="flex items-start justify-between gap-3 border-b border-border/60 bg-card/70 px-4 py-3">
            <div className="min-w-0 space-y-1">
                <div className="flex items-center gap-2">
                    <span className="text-sm font-semibold text-foreground">
                        Notifications
                    </span>
                    <Badge
                        variant="outline"
                        className="rounded-full border-border/50 bg-muted/30 px-2 py-0 text-[10px] font-semibold tracking-[0.12em] text-muted-foreground uppercase"
                    >
                        {hasUnread
                            ? `${formatNotificationBadgeCount(unreadCount)} unread`
                            : 'All caught up'}
                    </Badge>
                </div>
                <p className="text-[11px] leading-4 text-muted-foreground">
                    {loading
                        ? 'Refreshing your latest account and workflow updates.'
                        : 'Latest loan, access, and account activity in one place.'}
                </p>
            </div>
            {hasUnread ? (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onMarkAllAsRead}
                    className="h-8 shrink-0 rounded-full px-2.5 text-xs font-medium text-muted-foreground hover:bg-muted/50 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/40"
                >
                    <CheckCheck className="size-3.5" />
                    Mark all as read
                </Button>
            ) : null}
        </div>
    );
}

function NotificationSkeletonList() {
    return (
        <div className="space-y-2 p-2">
            {[0, 1, 2].map((item) => (
                <div
                    key={item}
                    className="flex items-start gap-3 rounded-xl border border-border/40 bg-card/50 px-3 py-3"
                >
                    <Skeleton className="size-10 rounded-xl" />
                    <div className="min-w-0 flex-1 space-y-2">
                        <div className="flex items-center gap-3">
                            <Skeleton className="h-4 w-36" />
                            <Skeleton className="ml-auto h-3 w-16" />
                        </div>
                        <Skeleton className="h-3 w-full" />
                        <Skeleton className="h-3 w-4/5" />
                        <div className="flex flex-wrap gap-1.5">
                            <Skeleton className="h-5 w-16 rounded-full" />
                            <Skeleton className="h-5 w-20 rounded-full" />
                            <Skeleton className="h-5 w-14 rounded-full" />
                        </div>
                    </div>
                </div>
            ))}
        </div>
    );
}

function NotificationStatePanel({
    Icon,
    title,
    message,
    action,
}: {
    Icon: LucideIcon;
    title: string;
    message: string;
    action?: ReactNode;
}) {
    return (
        <div
            className="flex flex-col items-center justify-center gap-3 px-6 py-10 text-center"
            role="status"
            aria-live="polite"
        >
            <div className="flex size-11 items-center justify-center rounded-2xl border border-border/50 bg-muted/30 text-muted-foreground">
                <Icon className="size-5" />
            </div>
            <div className="space-y-1">
                <p className="text-sm font-medium text-foreground">{title}</p>
                <p className="max-w-60 text-xs leading-5 text-muted-foreground">
                    {message}
                </p>
            </div>
            {action}
        </div>
    );
}

function NotificationMetadataChips({ chips }: { chips: NotificationChip[] }) {
    if (chips.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-wrap gap-1.5">
            {chips.map((chip) => (
                <Badge
                    key={chip.label}
                    variant="outline"
                    className={cn(
                        'max-w-full rounded-full px-2 py-0.5 text-[11px] font-medium',
                        chipClassNames[chip.tone ?? 'neutral'],
                    )}
                >
                    <span className="truncate">{chip.label}</span>
                </Badge>
            ))}
        </div>
    );
}

function NotificationListItem({
    notification,
    onSelect,
}: {
    notification: NotificationItem;
    onSelect: (notification: NotificationItem) => void;
}) {
    const payload = notification.data;
    const notes = payload.decision_notes?.trim();
    const cancellationReason = payload.cancellation_reason?.trim();
    const showCancellationReason =
        payload.type === 'loan_request_cancelled' &&
        Boolean(cancellationReason);
    const metadataChips = buildNotificationMetadataChips(payload);
    const timestamp = formatNotificationTimestamp(notification.created_at);
    const visual = getNotificationVisual(payload);
    const isUnread = notification.read_at === null;

    return (
        <DropdownMenuItem
            textValue={`${payload.title} ${payload.message}`}
            onSelect={() => onSelect(notification)}
            className={cn(
                'mx-2 my-1.5 cursor-pointer items-start gap-3 rounded-xl border px-3 py-3 transition-colors focus:text-foreground data-[highlighted]:text-foreground',
                'focus:bg-muted/50 data-[highlighted]:bg-muted/50 data-[highlighted]:ring-1 data-[highlighted]:ring-border/60',
                isUnread
                    ? 'border-primary/15 bg-primary/[0.05]'
                    : 'border-border/50 bg-background/70',
            )}
        >
            <div className="relative flex size-10 shrink-0 items-center justify-center rounded-xl border border-border/50 bg-background/80">
                <span
                    className={cn(
                        'flex size-8 items-center justify-center rounded-lg ring-1 ring-inset',
                        visual.className,
                    )}
                    aria-hidden="true"
                >
                    <visual.Icon className="size-4" />
                </span>
                {isUnread ? (
                    <span className="absolute -top-0.5 -right-0.5 size-2.5 rounded-full bg-primary ring-2 ring-popover" />
                ) : null}
            </div>

            <div className="min-w-0 flex-1 space-y-2">
                <div className="flex items-start gap-3">
                    <div className="min-w-0 flex-1">
                        <p
                            className={cn(
                                'line-clamp-1 text-sm leading-5',
                                isUnread
                                    ? 'font-semibold text-foreground'
                                    : 'font-medium text-foreground/90',
                            )}
                        >
                            {payload.title}
                        </p>
                    </div>
                    {timestamp ? (
                        <time
                            dateTime={timestamp.dateTime}
                            title={timestamp.title}
                            className="mt-0.5 flex shrink-0 items-center gap-1 text-[11px] font-medium text-muted-foreground"
                        >
                            <Clock3 className="size-3" />
                            <span>{timestamp.label}</span>
                        </time>
                    ) : null}
                </div>

                <p className="line-clamp-2 text-[13px] leading-5 text-muted-foreground">
                    {payload.message}
                </p>

                {showCancellationReason ? (
                    <div className="rounded-lg border border-rose-500/25 bg-rose-500/8 px-2.5 py-2 text-[12px] leading-5 text-rose-900 dark:text-rose-100">
                        <span className="mr-1 font-semibold">Reason:</span>
                        <span className="line-clamp-3">{cancellationReason}</span>
                    </div>
                ) : null}

                {notes ? (
                    <div className="rounded-lg border border-border/40 bg-background/70 px-2.5 py-2 text-[12px] leading-5 text-muted-foreground">
                        <span className="mr-1 font-medium text-foreground/75">
                            Note:
                        </span>
                        <span className="line-clamp-2">{notes}</span>
                    </div>
                ) : null}

                <NotificationMetadataChips chips={metadataChips} />
                <span className="sr-only">
                    {isUnread ? 'Unread' : 'Read'} notification.{' '}
                    {visual.iconLabel}.
                </span>
            </div>
        </DropdownMenuItem>
    );
}

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
        } catch {
            return;
        }
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
    const showLoadingState = loading && notifications.length === 0;
    const showErrorState = error !== null && notifications.length === 0;
    const showInlineError = error !== null && notifications.length > 0;
    const showEmptyState =
        !showLoadingState && error === null && notifications.length === 0;

    const handleOpenChange = (nextOpen: boolean) => {
        setOpen(nextOpen);

        if (nextOpen) {
            void loadNotifications();
            void loadUnreadCount();
        }
    };

    const handleNotificationSelect = useCallback(
        async (notification: NotificationItem) => {
            const destination = resolveNotificationDestination(notification.data);

            if (notification.read_at) {
                if (destination) {
                    setOpen(false);
                    router.visit(destination);
                }

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

                if (destination) {
                    setOpen(false);
                    router.visit(destination);
                }
            } catch {
                return;
            }
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
        } catch {
            return;
        }
    }, [hasUnread]);

    return (
        <DropdownMenu open={open} onOpenChange={handleOpenChange}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative rounded-full text-muted-foreground hover:bg-muted/50 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/40"
                    aria-label={
                        hasUnread
                            ? `Notifications, ${unreadCount} unread`
                            : 'Notifications'
                    }
                >
                    {hasUnread ? (
                        <BellDot className="size-5" />
                    ) : (
                        <Bell className="size-5" />
                    )}
                    {hasUnread ? (
                        <span className="absolute -top-0.5 -right-0.5 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-foreground px-1 text-[10px] font-semibold text-background ring-2 ring-background">
                            {formatNotificationBadgeCount(unreadCount)}
                        </span>
                    ) : null}
                </Button>
            </DropdownMenuTrigger>

            <DropdownMenuContent
                align="end"
                className="w-[23rem] rounded-2xl border-border/60 bg-popover/95 p-0 shadow-[0_16px_36px_rgba(15,23,42,0.12)] backdrop-blur-sm sm:w-[25.5rem]"
            >
                <NotificationHeader
                    hasUnread={hasUnread}
                    loading={loading}
                    unreadCount={unreadCount}
                    onMarkAllAsRead={() => void handleMarkAllAsRead()}
                />

                <div className="max-h-[28rem] overflow-y-auto overscroll-contain py-1">
                    {showInlineError ? (
                        <div className="mx-2 mt-1 rounded-xl border border-amber-500/15 bg-amber-500/6 px-3 py-2 text-[11px] leading-4 text-muted-foreground">
                            Unable to refresh right now. Showing the latest
                            loaded notifications.
                        </div>
                    ) : null}

                    {showLoadingState ? <NotificationSkeletonList /> : null}

                    {showErrorState ? (
                        <NotificationStatePanel
                            Icon={TriangleAlert}
                            title="Unable to load notifications"
                            message="Please try again in a moment. Your unread badge is still available in the header."
                            action={
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => void loadNotifications()}
                                    className="h-8 rounded-full px-3 text-xs"
                                >
                                    Try again
                                </Button>
                            }
                        />
                    ) : null}

                    {showEmptyState ? (
                        <NotificationStatePanel
                            Icon={Inbox}
                            title="You're all caught up"
                            message="New loan, account, and admin updates will appear here when there is something to review."
                        />
                    ) : null}

                    {!showLoadingState &&
                    !showErrorState &&
                    notifications.length > 0 ? (
                        <div className="py-1">
                            {notifications.map((notification) => (
                                <NotificationListItem
                                    key={notification.id}
                                    notification={notification}
                                    onSelect={(item) =>
                                        void handleNotificationSelect(item)
                                    }
                                />
                            ))}
                        </div>
                    ) : null}
                </div>

                <div className="border-t border-border/60 p-2">
                    <Button
                        asChild
                        variant="ghost"
                        size="sm"
                        className="h-9 w-full justify-between rounded-xl px-3 text-xs font-medium text-muted-foreground hover:bg-muted/50 hover:text-foreground"
                    >
                        <Link href="/notifications" onClick={() => setOpen(false)}>
                            View all notifications
                            <ArrowRight className="size-3.5" />
                        </Link>
                    </Button>
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

import { Head, router } from '@inertiajs/react';
import { CheckCheck, Clock3, Filter, Inbox, type LucideIcon, TriangleAlert } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';
import { PageHero } from '@/components/page-hero';
import { PageShell } from '@/components/page-shell';
import { SurfaceCard } from '@/components/surface-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/app-layout';
import { notificationsApi } from '@/lib/api/notifications';
import {
    buildNotificationMetadataChips,
    chipClassNames,
    formatNotificationTimestamp,
    getNotificationVisual,
    isAccountAccessNotification,
    isLoanRequestNotification,
    resolveNotificationDestination,
    type NotificationChip,
} from '@/lib/notification-ui';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import type { NotificationItem } from '@/types/notifications';

const FULL_PAGE_NOTIFICATIONS_LIMIT = 200;

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Notifications',
        href: '/notifications',
    },
];

type NotificationFilter = 'all' | 'unread' | 'loan' | 'account';
type NotificationGroupKey = 'today' | 'yesterday' | 'older';

const filterLabels: Record<NotificationFilter, string> = {
    all: 'All',
    unread: 'Unread',
    loan: 'Loan requests',
    account: 'Account / access',
};

const groupLabels: Record<NotificationGroupKey, string> = {
    today: 'Today',
    yesterday: 'Yesterday',
    older: 'Older',
};

const notificationBelongsToFilter = (
    notification: NotificationItem,
    filter: NotificationFilter,
): boolean => {
    if (filter === 'all') {
        return true;
    }

    if (filter === 'unread') {
        return notification.read_at === null;
    }

    if (filter === 'loan') {
        return isLoanRequestNotification(notification.data);
    }

    return isAccountAccessNotification(notification.data);
};

const resolveGroupKey = (createdAt?: string | null): NotificationGroupKey => {
    if (!createdAt) {
        return 'older';
    }

    const timestamp = new Date(createdAt);

    if (Number.isNaN(timestamp.getTime())) {
        return 'older';
    }

    const now = new Date();
    const startOfToday = new Date(
        now.getFullYear(),
        now.getMonth(),
        now.getDate(),
    );
    const startOfYesterday = new Date(startOfToday);
    startOfYesterday.setDate(startOfYesterday.getDate() - 1);

    if (timestamp >= startOfToday) {
        return 'today';
    }

    if (timestamp >= startOfYesterday) {
        return 'yesterday';
    }

    return 'older';
};

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
            className="flex flex-col items-center justify-center gap-3 px-6 py-12 text-center"
            role="status"
            aria-live="polite"
        >
            <div className="flex size-11 items-center justify-center rounded-2xl border border-border/50 bg-muted/30 text-muted-foreground">
                <Icon className="size-5" />
            </div>
            <div className="space-y-1">
                <p className="text-sm font-medium text-foreground">{title}</p>
                <p className="max-w-96 text-xs leading-5 text-muted-foreground">
                    {message}
                </p>
            </div>
            {action}
        </div>
    );
}

function NotificationsSkeletonList() {
    return (
        <div className="space-y-3 p-3">
            {[0, 1, 2, 3].map((item) => (
                <div
                    key={item}
                    className="flex items-start gap-3 rounded-xl border border-border/40 bg-card/50 px-3 py-3"
                >
                    <Skeleton className="size-10 rounded-xl" />
                    <div className="min-w-0 flex-1 space-y-2">
                        <div className="flex items-center gap-3">
                            <Skeleton className="h-4 w-40" />
                            <Skeleton className="ml-auto h-3 w-20" />
                        </div>
                        <Skeleton className="h-3 w-full" />
                        <Skeleton className="h-3 w-4/5" />
                        <div className="flex flex-wrap gap-1.5">
                            <Skeleton className="h-5 w-16 rounded-full" />
                            <Skeleton className="h-5 w-24 rounded-full" />
                            <Skeleton className="h-5 w-14 rounded-full" />
                        </div>
                    </div>
                </div>
            ))}
        </div>
    );
}

function NotificationCard({
    notification,
    onSelect,
}: {
    notification: NotificationItem;
    onSelect: (notification: NotificationItem) => void;
}) {
    const payload = notification.data;
    const notes = payload.decision_notes?.trim();
    const metadataChips = buildNotificationMetadataChips(payload);
    const timestamp = formatNotificationTimestamp(notification.created_at);
    const visual = getNotificationVisual(payload);
    const isUnread = notification.read_at === null;

    return (
        <button
            type="button"
            onClick={() => onSelect(notification)}
            className={cn(
                'w-full cursor-pointer rounded-xl border px-3 py-3 text-left transition-colors focus-visible:ring-2 focus-visible:ring-ring/40',
                'hover:bg-muted/50',
                isUnread
                    ? 'border-primary/15 bg-primary/[0.05]'
                    : 'border-border/50 bg-background/70',
            )}
            aria-label={`${isUnread ? 'Unread' : 'Read'} notification: ${payload.title}`}
        >
            <div className="flex items-start gap-3">
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
                        <span className="absolute -top-0.5 -right-0.5 size-2.5 rounded-full bg-primary ring-2 ring-background" />
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

                    {notes ? (
                        <div className="rounded-lg border border-border/40 bg-background/70 px-2.5 py-2 text-[12px] leading-5 text-muted-foreground">
                            <span className="mr-1 font-medium text-foreground/75">
                                Note:
                            </span>
                            <span className="line-clamp-3">{notes}</span>
                        </div>
                    ) : null}

                    <NotificationMetadataChips chips={metadataChips} />
                    <span className="sr-only">
                        {isUnread ? 'Unread' : 'Read'} notification.{' '}
                        {visual.iconLabel}.
                    </span>
                </div>
            </div>
        </button>
    );
}

export default function NotificationsPage() {
    const [notifications, setNotifications] = useState<NotificationItem[]>([]);
    const [unreadCount, setUnreadCount] = useState(0);
    const [activeFilter, setActiveFilter] = useState<NotificationFilter>('all');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const loadNotifications = useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            const [items, count] = await Promise.all([
                notificationsApi.getNotifications(FULL_PAGE_NOTIFICATIONS_LIMIT),
                notificationsApi.getUnreadCount(),
            ]);
            setNotifications(items);
            setUnreadCount(count);
        } catch {
            setError('Unable to load notifications right now.');
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        void loadNotifications();
    }, [loadNotifications]);

    const hasUnread = unreadCount > 0;
    const filteredNotifications = useMemo(
        () =>
            notifications.filter((notification) =>
                notificationBelongsToFilter(notification, activeFilter),
            ),
        [activeFilter, notifications],
    );

    const groupedNotifications = useMemo(() => {
        return filteredNotifications.reduce(
            (groups, notification) => {
                const key = resolveGroupKey(notification.created_at);
                groups[key].push(notification);

                return groups;
            },
            {
                today: [] as NotificationItem[],
                yesterday: [] as NotificationItem[],
                older: [] as NotificationItem[],
            },
        );
    }, [filteredNotifications]);

    const filterCounts = useMemo(
        () => ({
            all: notifications.length,
            unread: notifications.filter((item) => item.read_at === null).length,
            loan: notifications.filter((item) =>
                isLoanRequestNotification(item.data),
            ).length,
            account: notifications.filter((item) =>
                isAccountAccessNotification(item.data),
            ).length,
        }),
        [notifications],
    );

    const handleNotificationSelect = useCallback(
        async (notification: NotificationItem) => {
            const destination = resolveNotificationDestination(notification.data);

            if (notification.read_at === null) {
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
                } catch {
                    return;
                }
            }

            if (destination) {
                router.visit(destination);
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

    const showLoadingState = loading && notifications.length === 0;
    const showErrorState = error !== null && notifications.length === 0;
    const showInlineError = error !== null && notifications.length > 0;
    const showEmptyState =
        !showLoadingState && error === null && notifications.length === 0;
    const showFilteredEmptyState =
        !showLoadingState &&
        error === null &&
        notifications.length > 0 &&
        filteredNotifications.length === 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Notifications" />

            <PageShell size="wide" className="gap-6">
                <PageHero
                    kicker="Inbox"
                    title="Notifications"
                    description="Track loan, member, and access updates in one place."
                    badges={
                        <>
                            <Badge variant="outline">
                                {filterCounts.all} total
                            </Badge>
                            <Badge variant="outline">
                                {filterCounts.unread} unread
                            </Badge>
                        </>
                    }
                    rightSlot={
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => void loadNotifications()}
                                disabled={loading}
                            >
                                Refresh
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                onClick={() => void handleMarkAllAsRead()}
                                disabled={!hasUnread}
                            >
                                <CheckCheck className="size-3.5" />
                                Mark all as read
                            </Button>
                        </>
                    }
                />

                <SurfaceCard variant="default" padding="none" className="overflow-hidden">
                    <div className="border-b border-border/50 px-4 py-3">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground">
                                <Filter className="size-3.5" />
                                Filters:
                            </span>
                            {(Object.keys(filterLabels) as NotificationFilter[]).map(
                                (filter) => (
                                    <Button
                                        key={filter}
                                        type="button"
                                        size="sm"
                                        variant={
                                            activeFilter === filter
                                                ? 'secondary'
                                                : 'ghost'
                                        }
                                        onClick={() => setActiveFilter(filter)}
                                        className="h-8 rounded-full px-3 text-xs"
                                    >
                                        {filterLabels[filter]}
                                        <span className="ml-1 text-muted-foreground">
                                            ({filterCounts[filter]})
                                        </span>
                                    </Button>
                                ),
                            )}
                        </div>
                    </div>

                    {showInlineError ? (
                        <div className="mx-3 mt-3 rounded-xl border border-amber-500/15 bg-amber-500/6 px-3 py-2 text-[11px] leading-4 text-muted-foreground">
                            Unable to refresh right now. Showing the latest
                            loaded notifications.
                        </div>
                    ) : null}

                    {showLoadingState ? <NotificationsSkeletonList /> : null}

                    {showErrorState ? (
                        <NotificationStatePanel
                            Icon={TriangleAlert}
                            title="Unable to load notifications"
                            message="Please try again in a moment."
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
                            message="New notifications will appear here when there is something to review."
                        />
                    ) : null}

                    {showFilteredEmptyState ? (
                        <NotificationStatePanel
                            Icon={Filter}
                            title="No notifications in this filter"
                            message="Try another filter to see more notifications."
                        />
                    ) : null}

                    {!showLoadingState &&
                    !showErrorState &&
                    filteredNotifications.length > 0 ? (
                        <div className="space-y-4 p-3">
                            {(Object.keys(groupLabels) as NotificationGroupKey[]).map(
                                (groupKey) => {
                                    const items = groupedNotifications[groupKey];

                                    if (items.length === 0) {
                                        return null;
                                    }

                                    return (
                                        <section
                                            key={groupKey}
                                            className="space-y-2"
                                            aria-labelledby={`notifications-group-${groupKey}`}
                                        >
                                            <div className="flex items-center gap-2 px-1">
                                                <h2
                                                    id={`notifications-group-${groupKey}`}
                                                    className="text-xs font-semibold tracking-[0.08em] text-muted-foreground uppercase"
                                                >
                                                    {groupLabels[groupKey]}
                                                </h2>
                                                <Badge
                                                    variant="outline"
                                                    className="rounded-full px-1.5 py-0 text-[10px] text-muted-foreground"
                                                >
                                                    {items.length}
                                                </Badge>
                                            </div>
                                            <div className="space-y-2">
                                                {items.map((notification) => (
                                                    <NotificationCard
                                                        key={notification.id}
                                                        notification={notification}
                                                        onSelect={(item) =>
                                                            void handleNotificationSelect(
                                                                item,
                                                            )
                                                        }
                                                    />
                                                ))}
                                            </div>
                                        </section>
                                    );
                                },
                            )}
                        </div>
                    ) : null}
                </SurfaceCard>
            </PageShell>
        </AppLayout>
    );
}

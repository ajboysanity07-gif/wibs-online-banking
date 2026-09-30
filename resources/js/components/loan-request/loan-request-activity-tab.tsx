import { ArrowRight } from 'lucide-react';
import { LoanRequestStatusBadge } from '@/components/loan-request/loan-request-status-badge';
import { formatDateTime } from '@/lib/formatters';
import { isRecentAuditEntry } from '@/lib/loan-request-audit';
import { cn } from '@/lib/utils';
import type {
    LoanRequestAuditEntry,
    LoanRequestNotificationHistoryItem,
} from '@/types/loan-requests';

const cardClassName = 'rounded-xl border border-border bg-card shadow-card';

const notificationStatusTone = (status: string | null): string =>
    ({
        queued: 'border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-200',
        sending:
            'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-200',
        sent: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-200',
        failed: 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-200',
        skipped: 'border-border bg-muted/20 text-muted-foreground',
    })[status ?? ''] ?? 'border-border bg-muted/20 text-muted-foreground';

const statusLabel = (status: string | null): string =>
    status ? status.charAt(0).toUpperCase() + status.slice(1) : 'Unknown';

type Props = {
    auditTrail: LoanRequestAuditEntry[];
    notifications: LoanRequestNotificationHistoryItem[];
};

export function LoanRequestActivityTab({ auditTrail, notifications }: Props) {
    // Newest first; entries from the last 24h are "new" (bold, filled dot).
    const entries = [...auditTrail].reverse();

    return (
        <>
            <section
                className={cn(cardClassName, 'px-5 py-[18px]')}
                aria-label="Audit trail"
            >
                <div className="mb-1.5 flex items-center justify-between gap-2">
                    <h2 className="text-[17px] font-semibold">Audit trail</h2>
                    <span className="rounded-md border border-border bg-muted px-2 py-0.5 text-[11px] font-bold tracking-wide text-muted-foreground uppercase">
                        Staff view
                    </span>
                </div>
                {entries.length === 0 ? (
                    <p className="border-t border-border pt-3 text-sm text-muted-foreground">
                        No workflow history available yet.
                    </p>
                ) : (
                    <ol>
                        {entries.map((entry) => {
                            const isNew = isRecentAuditEntry(entry.created_at);

                            return (
                                <li
                                    key={`${entry.action}-${entry.id}`}
                                    className="flex flex-wrap gap-x-3.5 gap-y-1 border-t border-border py-3.5"
                                >
                                    <span
                                        aria-hidden="true"
                                        className={cn(
                                            'mt-1.5 size-2.5 shrink-0 rounded-full',
                                            isNew ? 'bg-primary' : 'bg-input',
                                        )}
                                    />
                                    <div className="min-w-0 flex-1 basis-52 space-y-1.5">
                                        <p
                                            className={cn(
                                                'text-[15px]',
                                                isNew
                                                    ? 'font-bold'
                                                    : 'font-medium',
                                            )}
                                        >
                                            {entry.action_label}
                                        </p>
                                        {entry.actor ? (
                                            <p className="text-[13px] text-muted-foreground">
                                                {entry.actor.name}
                                                {entry.actor.acctno
                                                    ? ` (Acct: ${entry.actor.acctno})`
                                                    : ''}
                                            </p>
                                        ) : null}
                                        {entry.from_status ||
                                        entry.to_status ? (
                                            <div className="flex flex-wrap items-center gap-2">
                                                {entry.from_status ? (
                                                    <LoanRequestStatusBadge
                                                        status={
                                                            entry.from_status
                                                        }
                                                        className="text-[11px]"
                                                    />
                                                ) : null}
                                                {entry.from_status &&
                                                entry.to_status ? (
                                                    <ArrowRight className="size-3.5 text-muted-foreground" />
                                                ) : null}
                                                {entry.to_status ? (
                                                    <LoanRequestStatusBadge
                                                        status={entry.to_status}
                                                        className="text-[11px]"
                                                    />
                                                ) : null}
                                            </div>
                                        ) : null}
                                        {entry.reason ? (
                                            <p className="rounded-lg border border-border bg-muted/10 p-2.5 text-sm whitespace-pre-wrap">
                                                <span className="mb-0.5 block text-[11px] font-semibold tracking-[0.16em] text-muted-foreground uppercase">
                                                    Remarks
                                                </span>
                                                {entry.reason}
                                            </p>
                                        ) : null}
                                        {entry.metadata.length > 0 ? (
                                            <div className="flex flex-wrap gap-2">
                                                {entry.metadata.map((item) => (
                                                    <span
                                                        key={`${entry.id}-${item.key}`}
                                                        className="inline-flex items-center gap-1.5 rounded-full border border-border bg-background/80 px-2.5 py-1 text-xs"
                                                    >
                                                        <span className="text-muted-foreground">
                                                            {item.label}
                                                        </span>
                                                        <span className="font-medium">
                                                            {item.value}
                                                        </span>
                                                    </span>
                                                ))}
                                            </div>
                                        ) : null}
                                    </div>
                                    <time className="w-full shrink-0 pl-6 text-[13px] text-muted-foreground sm:w-auto sm:pl-0 sm:text-right">
                                        {formatDateTime(entry.created_at)}
                                    </time>
                                </li>
                            );
                        })}
                    </ol>
                )}
            </section>

            <section
                className={cn(cardClassName, 'px-5 py-[18px]')}
                aria-label="Notification history"
            >
                <h2 className="text-[17px] font-semibold">
                    Notification history
                </h2>
                <p className="mb-1.5 text-[13px] text-muted-foreground">
                    Delivery state for workflow-triggered member notifications.
                </p>
                {notifications.length === 0 ? (
                    <p className="border-t border-border pt-3 text-sm text-muted-foreground">
                        No workflow notifications recorded yet.
                    </p>
                ) : (
                    <ul>
                        {notifications.map((event) => (
                            <li
                                key={event.id}
                                className="border-t border-border py-3"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-x-3 gap-y-1">
                                    <div className="min-w-0 space-y-0.5">
                                        <p className="text-sm font-semibold">
                                            {event.event_label}
                                        </p>
                                        <p className="text-[13px] text-muted-foreground">
                                            {event.channel}
                                            {event.recipient
                                                ? ` · ${event.recipient}`
                                                : ''}
                                            {' · '}Queued{' '}
                                            {event.queued_at
                                                ? formatDateTime(
                                                      event.queued_at,
                                                  )
                                                : '-'}
                                            {' · '}Sent{' '}
                                            {event.sent_at
                                                ? formatDateTime(event.sent_at)
                                                : '-'}
                                            {event.failed_at
                                                ? ` · Failed ${formatDateTime(event.failed_at)}`
                                                : ''}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Attempts {event.attempt_count} ·
                                            Retries {event.retry_count} ·
                                            Reminders {event.reminder_attempts}
                                        </p>
                                    </div>
                                    <span
                                        className={cn(
                                            'shrink-0 rounded-md border px-2 py-0.5 text-xs font-bold',
                                            notificationStatusTone(
                                                event.status,
                                            ),
                                        )}
                                    >
                                        {statusLabel(event.status)}
                                    </span>
                                </div>
                                {event.provider_error ? (
                                    <p className="mt-2 text-xs text-rose-700 dark:text-rose-300">
                                        {event.provider_error}
                                    </p>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </>
    );
}

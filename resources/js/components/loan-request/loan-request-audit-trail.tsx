import { ArrowRight, ChevronDown, History } from 'lucide-react';
import { useMemo, useState } from 'react';
import { LoanRequestStatusBadge } from '@/components/loan-request/loan-request-status-badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { formatDateTime } from '@/lib/formatters';
import { cn } from '@/lib/utils';
import type {
    LoanRequestAuditEntry,
    LoanRequestAuditTrailAudience,
} from '@/types/loan-requests';

type Props = {
    entries: LoanRequestAuditEntry[];
    audience?: LoanRequestAuditTrailAudience;
    compact?: boolean;
};

export function LoanRequestAuditTrail({
    entries,
    audience = 'staff',
    compact = false,
}: Props) {
    const [open, setOpen] = useState(true);
    const showStaffMetadata = audience === 'staff';
    const emptyCopy =
        audience === 'member'
            ? 'No workflow history is available yet.'
            : 'No workflow history available yet.';
    const orderedEntries = useMemo(() => [...entries].reverse(), [entries]);

    return (
        <Card className="border-border/30 bg-card/60 shadow-sm">
            <Collapsible open={open} onOpenChange={setOpen}>
                <CollapsibleTrigger asChild>
                    <CardHeader className="cursor-pointer select-none">
                        <div className="flex items-center gap-3">
                            <div className="rounded-full border border-border/50 bg-muted/20 p-2 text-muted-foreground">
                                <History className="size-4" />
                            </div>
                            <div className="flex-1 space-y-1">
                                <CardTitle>Audit trail</CardTitle>
                                <CardDescription>
                                    {audience === 'member'
                                        ? 'Track the safe workflow updates shared with you for this request.'
                                        : 'Review the full workflow history captured for this request.'}
                                </CardDescription>
                            </div>
                            <ChevronDown
                                className={cn(
                                    'size-4 shrink-0 text-muted-foreground transition-transform',
                                    open ? 'rotate-180' : '',
                                )}
                            />
                        </div>
                    </CardHeader>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <CardContent>
                        {entries.length === 0 ? (
                            <div className="rounded-xl border border-dashed border-border/60 bg-muted/10 px-4 py-6 text-sm text-muted-foreground">
                                {emptyCopy}
                            </div>
                        ) : (
                            <div className="relative">
                                <span
                                    aria-hidden="true"
                                    className="absolute top-2 bottom-2 left-[0.6875rem] w-px rounded-full bg-border/40"
                                />
                                <div className="space-y-5">
                                    {orderedEntries.map((entry) => (
                                        <div
                                            key={`${entry.action}-${entry.id}`}
                                            className="flex gap-3"
                                        >
                                            <div className="flex w-6 items-start justify-center">
                                                <span className="relative z-10 mt-1.5 size-3 rounded-full border border-primary/40 bg-primary/20" />
                                            </div>
                                            <div className="min-w-0 flex-1 space-y-2">
                                                <div
                                                    className={
                                                        compact
                                                            ? 'flex flex-col gap-1'
                                                            : 'flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between'
                                                    }
                                                >
                                                    <p className="text-sm font-semibold text-foreground">
                                                        {entry.action_label}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {formatDateTime(
                                                            entry.created_at,
                                                        )}
                                                    </p>
                                                </div>
                                                {showStaffMetadata &&
                                                entry.actor ? (
                                                    <p className="text-xs text-muted-foreground">
                                                        Actor:{' '}
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
                                                                status={
                                                                    entry.to_status
                                                                }
                                                                className="text-[11px]"
                                                            />
                                                        ) : null}
                                                    </div>
                                                ) : null}
                                                {entry.reason ? (
                                                    <div className="rounded-lg border border-border/40 bg-muted/10 p-3">
                                                        <p className="text-[11px] font-semibold tracking-[0.16em] text-muted-foreground uppercase">
                                                            Remarks
                                                        </p>
                                                        <p className="mt-1 text-sm whitespace-pre-wrap text-foreground">
                                                            {entry.reason}
                                                        </p>
                                                    </div>
                                                ) : null}
                                                {showStaffMetadata &&
                                                entry.metadata.length > 0 ? (
                                                    <div
                                                        className={
                                                            compact
                                                                ? 'grid gap-2'
                                                                : 'grid gap-2 sm:grid-cols-2'
                                                        }
                                                    >
                                                        {entry.metadata.map(
                                                            (metadataItem) => (
                                                                <div
                                                                    key={`${entry.id}-${metadataItem.key}`}
                                                                    className="rounded-lg border border-border/40 bg-background/80 p-3"
                                                                >
                                                                    <p className="text-[11px] font-semibold tracking-[0.16em] text-muted-foreground uppercase">
                                                                        {
                                                                            metadataItem.label
                                                                        }
                                                                    </p>
                                                                    <p className="mt-1 text-sm font-medium text-foreground">
                                                                        {
                                                                            metadataItem.value
                                                                        }
                                                                    </p>
                                                                </div>
                                                            ),
                                                        )}
                                                    </div>
                                                ) : null}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </CollapsibleContent>
            </Collapsible>
        </Card>
    );
}

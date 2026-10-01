import { Check, Download } from 'lucide-react';
import { useState, type FormEvent, type ReactNode } from 'react';
import {
    nextStepCopy,
    statusDescriptions,
} from '@/components/loan-request/loan-request-detail-page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDateTime } from '@/lib/formatters';
import type {
    GateCount,
    TaskException,
    TaskExceptionAction,
} from '@/lib/loan-request-gates';
import { cn } from '@/lib/utils';
import type {
    LoanRequestConditions,
    LoanRequestInternalNotes,
    LoanRequestStatusValue,
} from '@/types/loan-requests';

const cardClassName = 'rounded-xl border border-border bg-card p-4 shadow-card';

const groupLabelClassName =
    'px-[18px] pt-3 pb-0.5 text-[11px] font-bold tracking-[.14em] text-muted-foreground uppercase';

const rowClassName =
    'flex min-h-11 items-center gap-2.5 border-t border-border px-[18px] py-2.5 lg:min-h-0';

const smallActionClassName =
    'min-h-11 shrink-0 border-[1.5px] border-primary px-2.5 text-xs font-bold text-primary lg:min-h-8';

function StatusDot({ open }: { open: boolean }) {
    return (
        <span
            aria-hidden
            className={cn(
                'size-[9px] shrink-0 rounded-full',
                open ? 'bg-destructive' : 'bg-primary',
            )}
        />
    );
}

type TasksProps = {
    stage: 'processing' | 'handed-off' | 'other';
    summary: { done: number; total: number };
    exceptions: TaskException[];
    onException: (action: TaskExceptionAction) => void;
    conditions: LoanRequestConditions;
    /** Condition key currently being saved, if any. */
    conditionPendingKey: string | null;
    onToggleCondition: (key: string, verified: boolean) => void;
    documents: GateCount;
    onOpenDocuments: () => void;
    /** Rendered only for viewers the page lets recommend. */
    recommend: {
        disabledReason: string | null;
        note: string;
        onClick: () => void;
    } | null;
};

/**
 * Processor task list before hand-off. Exceptions and document status are
 * derived from live data; conditions are signed off (and audited) server-side.
 * The footer button only ever ADDS a disabled state; the server re-validates.
 */
export function LoanRequestTasksCard({
    stage,
    summary,
    exceptions,
    onException,
    conditions,
    conditionPendingKey,
    onToggleCondition,
    documents,
    onOpenDocuments,
    recommend,
}: TasksProps) {
    if (stage === 'other') {
        return null;
    }

    if (stage === 'handed-off') {
        return (
            <section className={cardClassName}>
                <h2 className="text-base font-semibold">
                    With the Loan Manager
                </h2>
                <p className="mt-2 text-[13px] leading-relaxed text-muted-foreground">
                    The processor has recommended this request. The request is
                    locked for processing, and the Loan Manager decides.
                </p>
            </section>
        );
    }

    const percent =
        summary.total === 0 ? 100 : (summary.done / summary.total) * 100;
    const documentsCurrent = documents.done === documents.total;
    const readOnlyConditions = !conditions.can_verify;

    return (
        <section
            aria-label="Tasks"
            className="overflow-hidden rounded-xl border border-border bg-card shadow-card"
        >
            <div className="px-[18px] pt-3.5 pb-3">
                <div className="flex items-center justify-between">
                    <h2 className="text-base font-semibold">Tasks</h2>
                    <span className="text-[13px] font-bold text-muted-foreground tabular-nums">
                        {summary.done} of {summary.total}
                    </span>
                </div>
                <div
                    role="progressbar"
                    aria-label="Tasks done"
                    aria-valuemin={0}
                    aria-valuemax={summary.total}
                    aria-valuenow={summary.done}
                    className="mt-2.5 h-1.5 overflow-hidden rounded-[3px] bg-border"
                >
                    <div
                        className="h-full bg-primary transition-[width] duration-300 motion-reduce:transition-none"
                        style={{ width: `${percent}%` }}
                    />
                </div>
            </div>

            <p className={groupLabelClassName}>Exceptions</p>
            {exceptions.length === 0 ? (
                <p className="border-t border-border px-[18px] py-2.5 text-xs text-muted-foreground">
                    No blocking exceptions.
                </p>
            ) : (
                exceptions.map((exception) => {
                    const action = exception.open ? exception.action : null;

                    return (
                        <div key={exception.key} className={rowClassName}>
                            <StatusDot open={exception.open} />
                            <div className="min-w-0 flex-1">
                                <p className="text-[13px] font-bold">
                                    {exception.title}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {exception.description}
                                </p>
                            </div>
                            {action ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className={smallActionClassName}
                                    onClick={() => onException(action.key)}
                                >
                                    {action.label}
                                </Button>
                            ) : null}
                            {!exception.open ? (
                                <Check
                                    aria-label="Resolved"
                                    className="size-4 shrink-0 text-primary"
                                />
                            ) : null}
                        </div>
                    );
                })
            )}

            <p className={groupLabelClassName}>Conditions</p>
            {conditions.available ? (
                <ul>
                    {conditions.items.map((item) => (
                        <li key={item.key}>
                            <label
                                className={cn(
                                    rowClassName,
                                    'py-2',
                                    readOnlyConditions
                                        ? 'cursor-default'
                                        : 'cursor-pointer',
                                )}
                            >
                                <input
                                    type="checkbox"
                                    className="peer sr-only"
                                    checked={item.verified}
                                    disabled={
                                        readOnlyConditions ||
                                        conditionPendingKey === item.key
                                    }
                                    onChange={(event) =>
                                        onToggleCondition(
                                            item.key,
                                            event.target.checked,
                                        )
                                    }
                                />
                                <span
                                    aria-hidden
                                    className={cn(
                                        'grid size-[18px] shrink-0 place-items-center rounded-[5px] border-[1.5px] peer-focus-visible:ring-2 peer-focus-visible:ring-ring/50',
                                        item.verified
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-input bg-card',
                                        conditionPendingKey === item.key &&
                                            'opacity-60',
                                    )}
                                >
                                    {item.verified ? (
                                        <Check className="size-3" />
                                    ) : null}
                                </span>
                                <span className="min-w-0">
                                    <span className="block text-[13px] font-semibold">
                                        {item.label}
                                    </span>
                                    <span className="block text-[11px] text-muted-foreground">
                                        {item.verified
                                            ? `${item.verified_by ?? 'Staff'}${
                                                  item.verified_at
                                                      ? ` · ${formatDateTime(item.verified_at)}`
                                                      : ''
                                              }`
                                            : 'Not verified'}
                                    </span>
                                </span>
                            </label>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="border-t border-border px-[18px] py-2.5 text-xs text-muted-foreground">
                    Condition sign-off is pending deployment. It does not block
                    recommending approval until it is available.
                </p>
            )}
            {conditions.available && readOnlyConditions ? (
                <p className="px-[18px] pb-1 text-[11px] text-muted-foreground">
                    Only the assigned Loan Processor can sign these off.
                </p>
            ) : null}

            <p className={groupLabelClassName}>Documents</p>
            <div className={rowClassName}>
                <StatusDot open={!documentsCurrent} />
                <div className="min-w-0 flex-1">
                    <p className="text-[13px] font-bold">Package current</p>
                    <p className="text-xs text-muted-foreground tabular-nums">
                        {documents.done} of {documents.total} current
                    </p>
                </div>
                {documentsCurrent ? (
                    <Check
                        aria-label="Done"
                        className="size-4 shrink-0 text-primary"
                    />
                ) : (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className={smallActionClassName}
                        onClick={onOpenDocuments}
                    >
                        Open
                    </Button>
                )}
            </div>

            {recommend ? (
                <div className="flex flex-col gap-2.5 border-t border-border bg-muted px-[18px] py-3.5">
                    <Button
                        type="button"
                        className={cn(
                            'min-h-11 w-full font-bold',
                            recommend.disabledReason &&
                                'cursor-not-allowed opacity-45 hover:bg-primary',
                        )}
                        aria-disabled={recommend.disabledReason !== null}
                        title={recommend.disabledReason ?? undefined}
                        onClick={() => {
                            if (recommend.disabledReason === null) {
                                recommend.onClick();
                            }
                        }}
                    >
                        Recommend approval
                    </Button>
                    <p className="text-xs leading-normal text-muted-foreground">
                        {recommend.note}
                    </p>
                </div>
            ) : null}
        </section>
    );
}

type StatusProps = {
    status: LoanRequestStatusValue | null;
    isV2: boolean;
    notificationsSent: number;
    pdfHref: string | null;
};

/** What the record header doesn't already show: workflow, notifications, next step, PDF. */
export function LoanRequestStatusRailCard({
    status,
    isV2,
    notificationsSent,
    pdfHref,
}: StatusProps) {
    const facts: [string, ReactNode][] = [
        ['Workflow', isV2 ? 'Document Workflow v2' : 'Legacy v1'],
        ['Notifications', `${notificationsSent} sent`],
    ];
    const key = (status ?? 'draft') as LoanRequestStatusValue;

    return (
        <section className={cn(cardClassName, 'space-y-3')}>
            <h2 className="text-base font-semibold">Request status</h2>
            <dl>
                {facts.map(([label, value]) => (
                    <div
                        key={label}
                        className="flex justify-between gap-3 border-t border-border py-2 text-sm"
                    >
                        <dt className="text-muted-foreground">{label}</dt>
                        <dd className="text-right font-semibold">{value}</dd>
                    </div>
                ))}
            </dl>
            <div className="rounded-lg border border-primary/25 bg-primary/10 px-3 py-2.5 text-[13px] leading-relaxed text-foreground">
                <p>
                    <b>{statusDescriptions[key]}</b>
                </p>
                <p className="mt-1">
                    <b>What happens next:</b> {nextStepCopy(key)}
                </p>
            </div>
            {pdfHref ? (
                <a
                    href={pdfHref}
                    className="inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-primary hover:underline lg:min-h-9"
                >
                    <Download className="size-4" />
                    Download PDF
                </a>
            ) : null}
        </section>
    );
}

type NotesProps = {
    notes: LoanRequestInternalNotes;
    /** Resolves true when saved, so the input clears only then. */
    onAdd: (body: string) => Promise<boolean>;
};

/** Staff-only notes; the page never receives them for members or the applicant. */
export function LoanRequestInternalNotesCard({ notes, onAdd }: NotesProps) {
    const [draft, setDraft] = useState('');
    const [isSaving, setIsSaving] = useState(false);

    if (!notes.available) {
        return null;
    }

    const submit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const body = draft.trim();

        if (body === '' || isSaving) {
            return;
        }

        setIsSaving(true);

        if (await onAdd(body)) {
            setDraft('');
        }

        setIsSaving(false);
    };

    return (
        <section
            aria-label="Internal notes"
            className={cn(cardClassName, 'flex flex-col gap-2.5 px-[18px]')}
        >
            <h2 className="text-base font-semibold">Internal notes</h2>
            {notes.can_add ? (
                <form className="flex gap-2" onSubmit={submit}>
                    <Input
                        aria-label="New internal note"
                        placeholder="Add a note for staff"
                        maxLength={2000}
                        value={draft}
                        onChange={(event) => setDraft(event.target.value)}
                        className="h-11 min-w-0 flex-1 text-[13px] lg:h-[38px]"
                    />
                    <Button
                        type="submit"
                        className="h-11 bg-foreground px-3 text-[13px] font-bold text-background hover:bg-foreground/90 lg:h-[38px]"
                        disabled={draft.trim() === '' || isSaving}
                    >
                        Add
                    </Button>
                </form>
            ) : null}
            {notes.items.length === 0 ? (
                <p className="text-xs text-muted-foreground">No notes yet.</p>
            ) : (
                <ul>
                    {notes.items.map((note) => (
                        <li
                            key={note.id}
                            className="border-t border-border pt-2 pb-1"
                        >
                            <p className="text-[11px] text-muted-foreground">
                                {note.author ?? 'Staff'}
                                {note.created_at
                                    ? ` · ${formatDateTime(note.created_at)}`
                                    : ''}
                            </p>
                            <p className="text-[13px] leading-[1.45] break-words whitespace-pre-line">
                                {note.body}
                            </p>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

import { Download } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    nextStepCopy,
    statusDescriptions,
} from '@/components/loan-request/loan-request-detail-page';
import type { GateCount, RecommendGates } from '@/lib/loan-request-gates';
import { cn } from '@/lib/utils';
import type { LoanRequestStatusValue } from '@/types/loan-requests';

const cardClassName = 'rounded-xl border border-border bg-card p-4 shadow-card';

const Pill = ({
    tone,
    children,
}: {
    tone: 'ok' | 'bad';
    children: ReactNode;
}) => (
    <span
        className={cn(
            'rounded-md px-2 py-0.5 text-xs font-bold',
            tone === 'ok'
                ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-200'
                : 'bg-destructive/10 text-destructive',
        )}
    >
        {children}
    </span>
);

function GateBar({ label, gate }: { label: string; gate: GateCount }) {
    const clear = gate.done === gate.total;
    const percent = gate.total === 0 ? 100 : (gate.done / gate.total) * 100;

    return (
        <div>
            <div className="mb-1 flex justify-between text-[13px]">
                <span className="font-medium">{label}</span>
                <span className="text-muted-foreground tabular-nums">
                    {gate.done} of {gate.total}
                </span>
            </div>
            <div
                role="progressbar"
                aria-label={label}
                aria-valuemin={0}
                aria-valuemax={gate.total}
                aria-valuenow={gate.done}
                className="h-1.5 overflow-hidden rounded-full bg-muted"
            >
                <div
                    className={cn(
                        'h-full rounded-full transition-[width] motion-reduce:transition-none',
                        clear ? 'bg-primary' : 'bg-amber-500',
                    )}
                    style={{ width: `${percent}%` }}
                />
            </div>
        </div>
    );
}

type ReadyProps = {
    stage: 'processing' | 'handed-off' | 'other';
    /** Gates only apply to Document Workflow v2. */
    gated: boolean;
    gates: RecommendGates;
};

/** Gates before hand-off; maker-checker: processor recommends, manager decides. */
export function LoanRequestReadyCard({ stage, gated, gates }: ReadyProps) {
    if (stage === 'other') {
        return null;
    }

    return (
        <section className={cardClassName}>
            <div className="flex items-center justify-between gap-2">
                <h2 className="text-base font-semibold">
                    {stage === 'processing'
                        ? 'Ready to recommend?'
                        : 'With the Loan Manager'}
                </h2>
                {stage === 'processing' && gated ? (
                    gates.openCount === 0 ? (
                        <Pill tone="ok">Ready</Pill>
                    ) : (
                        <Pill tone="bad">
                            {gates.openCount} gate
                            {gates.openCount === 1 ? '' : 's'} open
                        </Pill>
                    )
                ) : null}
            </div>
            {stage === 'processing' && gated ? (
                <div className="mt-3 space-y-3">
                    {gates.conditions ? (
                        <GateBar
                            label="Conditions verified"
                            gate={gates.conditions}
                        />
                    ) : null}
                    <GateBar
                        label="Exceptions cleared"
                        gate={gates.exceptions}
                    />
                    <GateBar label="Documents current" gate={gates.documents} />
                </div>
            ) : null}
            <p className="mt-3 text-[13px] leading-relaxed text-muted-foreground">
                {stage === 'processing' && !gated
                    ? 'Gates apply to Document Workflow v2 requests. '
                    : ''}
                {stage === 'handed-off'
                    ? 'The processor has recommended this request. The request is locked for processing, and the Loan Manager decides.'
                    : 'The Loan Manager decides. The request locks for processing after you recommend it.'}
            </p>
        </section>
    );
}

type StatusProps = {
    status: LoanRequestStatusValue | null;
    isV2: boolean;
    assignedTo: string | null;
    ageDays: number | null;
    ageTargetDays: number;
    notificationsSent: number;
    pdfHref: string | null;
};

export function LoanRequestStatusRailCard({
    status,
    isV2,
    assignedTo,
    ageDays,
    ageTargetDays,
    notificationsSent,
    pdfHref,
}: StatusProps) {
    const facts: [string, ReactNode][] = [
        ['Workflow', isV2 ? 'Document Workflow v2' : 'Legacy v1'],
        ['Assigned to', assignedTo ?? 'Unassigned'],
        [
            'Age',
            ageDays === null ? (
                '-'
            ) : (
                <span
                    className={cn(
                        ageDays >= ageTargetDays && 'text-destructive',
                    )}
                >
                    {ageDays}d
                </span>
            ),
        ],
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

type HealthProps = {
    issueCount: number;
    ageDays: number | null;
    ageIssue: boolean;
    pendingMemberAction: boolean;
};

export function LoanRequestHealthCard({
    issueCount,
    ageDays,
    ageIssue,
    pendingMemberAction,
}: HealthProps) {
    return (
        <section className={cn(cardClassName, 'space-y-3')}>
            <div className="flex items-center justify-between gap-2">
                <h2 className="text-base font-semibold">Workflow health</h2>
                {issueCount === 0 ? (
                    <Pill tone="ok">All clear</Pill>
                ) : (
                    <Pill tone="bad">
                        {issueCount} issue{issueCount === 1 ? '' : 's'}
                    </Pill>
                )}
            </div>
            <div className="grid grid-cols-2 gap-3">
                <div>
                    <p className="text-[11px] font-semibold tracking-widest text-muted-foreground uppercase">
                        Processing age
                    </p>
                    <p
                        className={cn(
                            'text-[22px] font-bold',
                            ageIssue && 'text-destructive',
                        )}
                    >
                        {ageDays === null ? '-' : `${ageDays}d`}
                    </p>
                </div>
                <div>
                    <p className="text-[11px] font-semibold tracking-widest text-muted-foreground uppercase">
                        Member action
                    </p>
                    <p
                        className={cn(
                            'text-[22px] font-bold',
                            pendingMemberAction && 'text-destructive',
                        )}
                    >
                        {pendingMemberAction ? 'Pending' : 'None'}
                    </p>
                </div>
            </div>
        </section>
    );
}

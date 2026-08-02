import {
    AlertCircle,
    CheckCircle2,
    Circle,
    ClipboardCheck,
    Clock,
    MinusCircle,
    MoreHorizontal,
    PlayCircle,
    RefreshCw,
    XCircle,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatDateTime } from '@/lib/formatters';
import { cn } from '@/lib/utils';
import type {
    LoanRequestDocumentChecklistItem,
    LoanRequestDocumentReadinessStatus,
} from '@/types/loan-requests';

const WORKBOOK_DOCUMENT_KEYS = [
    'loan_information',
    'plan_of_payment',
    'disclosure_statement',
    'promissory_note',
];

const checklistStatusIcon = (status: LoanRequestDocumentReadinessStatus) => {
    switch (status) {
        case 'not_started':
            return { Icon: Circle, className: 'text-muted-foreground' };
        case 'incomplete':
            return {
                Icon: AlertCircle,
                className: 'text-amber-600 dark:text-amber-300',
            };
        case 'awaiting_member_confirmation':
            return {
                Icon: Clock,
                className: 'text-violet-600 dark:text-violet-300',
            };
        case 'ready_to_generate':
            return {
                Icon: PlayCircle,
                className: 'text-sky-600 dark:text-sky-300',
            };
        case 'generated_current':
            return {
                Icon: CheckCircle2,
                className: 'text-emerald-600 dark:text-emerald-300',
            };
        case 'generated_stale':
            return {
                Icon: RefreshCw,
                className: 'text-amber-600 dark:text-amber-300',
            };
        case 'generation_failed':
            return {
                Icon: XCircle,
                className: 'text-rose-600 dark:text-rose-300',
            };
        case 'not_applicable':
            return { Icon: MinusCircle, className: 'text-muted-foreground' };
        case 'legacy_data_incomplete':
            return {
                Icon: AlertCircle,
                className: 'text-amber-600 dark:text-amber-300',
            };
        default:
            return { Icon: Circle, className: 'text-muted-foreground' };
    }
};

export type LoanRequestDocumentChecklistCardProps = {
    documentChecklist: LoanRequestDocumentChecklistItem[];
    generatedDocumentBaseHref: string;
    canGenerateDocuments: boolean;
    isProcessing: boolean;
    onGenerate: (documentKey: string) => void;
};

export const LoanRequestDocumentChecklistCard = ({
    documentChecklist,
    generatedDocumentBaseHref,
    canGenerateDocuments,
    isProcessing,
    onGenerate,
}: LoanRequestDocumentChecklistCardProps) => (
    <Card className="border-border/30 bg-card/70 shadow-sm">
        <CardHeader>
            <CardTitle className="flex items-center gap-2">
                <ClipboardCheck className="size-4 text-muted-foreground" />
                Document checklist
            </CardTitle>
            <CardDescription>
                Every applicable document must be current before
                recommendation.
            </CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col divide-y divide-border/40">
            {[...documentChecklist]
                .sort((a, b) => {
                    const aIncomplete = a.blockers.length > 0 ? 1 : 0;
                    const bIncomplete = b.blockers.length > 0 ? 1 : 0;

                    return bIncomplete - aIncomplete;
                })
                .map((document) => {
                    const viewHref = `${generatedDocumentBaseHref}/${document.key}`;
                    const isWorkbookDocument = WORKBOOK_DOCUMENT_KEYS.includes(
                        document.key,
                    );
                    const previewHref = isWorkbookDocument
                        ? `${viewHref}?preview=1`
                        : viewHref;
                    const printDocumentHref = isWorkbookDocument
                        ? `${viewHref}?print=1`
                        : viewHref;
                    const downloadHref = `${viewHref}?download=1`;

                    const hasBeenGenerated =
                        (document.generated_version ?? 0) > 0;
                    const missingFieldCount = document.blockers.length;
                    const { Icon: StatusIcon, className: statusIconClassName } =
                        checklistStatusIcon(document.status);
                    const subtitle = document.template_version ?? document.key;
                    const showWitnessTwoCaveat =
                        document.key === 'loan_information' ||
                        document.key === 'promissory_note';

                    return (
                        <div
                            key={document.key}
                            className="flex flex-col gap-2 py-3 first:pt-0 last:pb-0"
                        >
                            <div className="flex items-center justify-between gap-3">
                                <div className="flex min-w-0 items-center gap-2">
                                    <StatusIcon
                                        className={cn(
                                            'size-4 shrink-0',
                                            statusIconClassName,
                                        )}
                                    />
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-semibold">
                                            {document.label}
                                        </p>
                                        <p className="truncate text-xs text-muted-foreground">
                                            {subtitle}
                                        </p>
                                        {showWitnessTwoCaveat && (
                                            <p className="truncate text-xs text-muted-foreground/70">
                                                Witness 2 recorded
                                                automatically at approval if
                                                left blank
                                            </p>
                                        )}
                                    </div>
                                </div>
                                <div className="flex shrink-0 items-center gap-2">
                                    {missingFieldCount > 0 ? (
                                        <span className="text-xs text-muted-foreground">
                                            {missingFieldCount} field
                                            {missingFieldCount === 1
                                                ? ''
                                                : 's'}{' '}
                                            missing
                                        </span>
                                    ) : null}
                                    {document.generated_filename ? (
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                >
                                                    <MoreHorizontal className="size-4" />
                                                    <span className="sr-only">
                                                        Document actions
                                                    </span>
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem asChild>
                                                    <a
                                                        href={previewHref}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        Preview
                                                    </a>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem asChild>
                                                    <a
                                                        href={
                                                            printDocumentHref
                                                        }
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        Print
                                                    </a>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem asChild>
                                                    <a href={downloadHref}>
                                                        Download
                                                    </a>
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    ) : null}
                                    {canGenerateDocuments &&
                                    document.is_applicable ? (
                                        <Button
                                            type="button"
                                            size="sm"
                                            disabled={isProcessing}
                                            onClick={() =>
                                                onGenerate(document.key)
                                            }
                                        >
                                            Regenerate
                                        </Button>
                                    ) : null}
                                </div>
                            </div>
                            {document.generated_at || hasBeenGenerated ? (
                                <div className="flex flex-wrap items-center gap-3 pl-6 text-[11px] text-muted-foreground">
                                    {document.generated_at ? (
                                        <span>
                                            Last generated:{' '}
                                            {formatDateTime(
                                                document.generated_at,
                                            )}
                                        </span>
                                    ) : null}
                                    {hasBeenGenerated ? (
                                        <span>
                                            Generated by:{' '}
                                            {document.generated_by ?? '-'} (v
                                            {document.generated_version}
                                            {document.source_version
                                                ? `, source v${document.source_version}`
                                                : ''}
                                            )
                                        </span>
                                    ) : null}
                                </div>
                            ) : null}
                            {document.failure_message &&
                            document.status !== 'incomplete' ? (
                                <p className="pl-6 text-[11px] font-medium text-rose-700 dark:text-rose-200">
                                    {document.failure_message}
                                </p>
                            ) : null}
                        </div>
                    );
                })}
        </CardContent>
    </Card>
);

import {
    AlertCircle,
    CheckCircle2,
    Circle,
    ClipboardCheck,
    Clock,
    Download,
    Eye,
    Info,
    MinusCircle,
    MoreHorizontal,
    PlayCircle,
    Printer,
    RefreshCw,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Spinner } from '@/components/ui/spinner';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
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
    onGenerate: (documentKeys: string[]) => Promise<void>;
    onRegenerate: (documentKey: string) => Promise<void>;
    packageZipHref?: string | null;
    lockFinalizedDocuments?: boolean;
};

export const LoanRequestDocumentChecklistCard = ({
    documentChecklist,
    generatedDocumentBaseHref,
    canGenerateDocuments,
    isProcessing,
    onGenerate,
    onRegenerate,
    packageZipHref = null,
    lockFinalizedDocuments = false,
}: LoanRequestDocumentChecklistCardProps) => {
    const [selectedKeys, setSelectedKeys] = useState<Set<string>>(new Set());
    const [hideNotApplicable, setHideNotApplicable] = useState(true);
    const [confirmRegenerateKey, setConfirmRegenerateKey] = useState<
        string | null
    >(null);
    // Tracks which documents are actively being generated. Bulk generation
    // issues one sequential request per document (see
    // submitGenerateSelectedDocuments in the parent page), so the shared
    // isProcessing flag toggles false/true between each one -- it's not a
    // reliable "the whole batch is done" signal. Awaiting the onGenerate/
    // onRegenerate promise directly (below) is the only accurate way to know
    // when this component's own request(s) have actually finished.
    const [pendingKeys, setPendingKeys] = useState<Set<string>>(new Set());

    const sortedChecklist = [...documentChecklist]
        .filter((document) => !hideNotApplicable || document.is_applicable)
        .sort((a, b) => {
            const aIncomplete = a.blockers.length > 0 ? 1 : 0;
            const bIncomplete = b.blockers.length > 0 ? 1 : 0;

            return bIncomplete - aIncomplete;
        });

    const isDocumentLocked = (
        document: LoanRequestDocumentChecklistItem,
    ): boolean =>
        lockFinalizedDocuments && document.status === 'generated_current';

    const selectableKeys = sortedChecklist
        .filter(
            (document) => document.is_applicable && !isDocumentLocked(document),
        )
        .map((document) => document.key);
    const allSelected =
        selectableKeys.length > 0 &&
        selectableKeys.every((key) => selectedKeys.has(key));

    const toggleKey = (key: string, checked: boolean) => {
        setSelectedKeys((current) => {
            const next = new Set(current);

            if (checked) {
                next.add(key);
            } else {
                next.delete(key);
            }

            return next;
        });
    };

    const toggleSelectAll = (checked: boolean) => {
        setSelectedKeys(checked ? new Set(selectableKeys) : new Set());
    };

    const handleGenerateSelected = async () => {
        const keys = [...selectedKeys];
        setPendingKeys(new Set(keys));
        setSelectedKeys(new Set());

        try {
            await onGenerate(keys);
        } finally {
            setPendingKeys(new Set());
        }
    };

    const relaxedEntries = sortedChecklist.filter(
        (document) =>
            document.is_relaxed_old_record &&
            document.manual_fill_fields.length > 0,
    );

    const applicableDocuments = documentChecklist.filter(
        (document) => document.is_applicable,
    );
    const allDocumentsGenerated =
        applicableDocuments.length > 0 &&
        applicableDocuments.every(
            (document) => (document.generated_version ?? 0) > 0,
        );

    return (
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
                <div className="flex items-center justify-between gap-3 pt-1">
                    <Label className="flex items-center gap-2 text-xs font-normal text-muted-foreground">
                        <Checkbox
                            checked={hideNotApplicable}
                            onCheckedChange={(checked) =>
                                setHideNotApplicable(checked === true)
                            }
                        />
                        Hide not applicable
                    </Label>
                    {canGenerateDocuments ? (
                        <Button
                            type="button"
                            size="sm"
                            disabled={isProcessing || selectedKeys.size === 0}
                            onClick={handleGenerateSelected}
                        >
                            {pendingKeys.size > 0 ? (
                                <>
                                    <Spinner />
                                    Generating ({pendingKeys.size})
                                </>
                            ) : (
                                <>
                                    Generate selected
                                    {selectedKeys.size > 0
                                        ? ` (${selectedKeys.size})`
                                        : ''}
                                </>
                            )}
                        </Button>
                    ) : null}
                </div>
            </CardHeader>
            <CardContent className="flex flex-col">
                {relaxedEntries.length > 0 ? (
                    <Alert variant="warning" className="mb-3">
                        <AlertCircle className="size-4" />
                        <AlertTitle>Manual fill required at release</AlertTitle>
                        <AlertDescription>
                            <p>
                                Old record — the following fields are blank on
                                the generated documents and must be filled out
                                manually by the member in person during release.
                            </p>
                            {relaxedEntries.map((document) => (
                                <div key={document.key} className="mt-1">
                                    <p className="font-medium">
                                        {document.label}
                                    </p>
                                    <ul className="list-disc pl-5">
                                        {document.manual_fill_fields.map(
                                            (field) => (
                                                <li key={field}>{field}</li>
                                            ),
                                        )}
                                    </ul>
                                </div>
                            ))}
                        </AlertDescription>
                    </Alert>
                ) : null}
                {canGenerateDocuments && selectableKeys.length > 0 ? (
                    <Label className="flex items-center gap-2 pb-2 text-xs font-normal text-muted-foreground">
                        <Checkbox
                            checked={allSelected}
                            onCheckedChange={(checked) =>
                                toggleSelectAll(checked === true)
                            }
                        />
                        Select all
                    </Label>
                ) : null}
                <div className="flex flex-col divide-y divide-border/40">
                    {sortedChecklist.map((document) => {
                        const viewHref = `${generatedDocumentBaseHref}/${document.key}`;
                        const isWorkbookDocument =
                            WORKBOOK_DOCUMENT_KEYS.includes(document.key);
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
                        const {
                            Icon: StatusIcon,
                            className: statusIconClassName,
                        } = checklistStatusIcon(document.status);
                        const subtitle =
                            document.template_version ?? document.key;
                        const showWitnessTwoCaveat =
                            document.key === 'loan_information' ||
                            document.key === 'promissory_note';
                        const hasMetadata =
                            document.generated_at || hasBeenGenerated;
                        const isPending = pendingKeys.has(document.key);
                        const isLocked = isDocumentLocked(document);

                        return (
                            <div
                                key={document.key}
                                className="flex flex-col gap-2 py-3 first:pt-0 last:pb-0"
                            >
                                <div className="flex items-center justify-between gap-3">
                                    <div className="flex min-w-0 items-center gap-2">
                                        {canGenerateDocuments ? (
                                            <Checkbox
                                                className="shrink-0"
                                                checked={selectedKeys.has(
                                                    document.key,
                                                )}
                                                disabled={
                                                    !document.is_applicable ||
                                                    isPending ||
                                                    isLocked
                                                }
                                                onCheckedChange={(checked) =>
                                                    toggleKey(
                                                        document.key,
                                                        checked === true,
                                                    )
                                                }
                                            />
                                        ) : null}
                                        {isPending ? (
                                            <Spinner className="size-4 shrink-0 text-muted-foreground" />
                                        ) : (
                                            <StatusIcon
                                                className={cn(
                                                    'size-4 shrink-0',
                                                    statusIconClassName,
                                                )}
                                            />
                                        )}
                                        <div className="min-w-0">
                                            <p className="flex items-center gap-1.5 truncate text-sm font-semibold">
                                                {document.label}
                                                {!document.is_applicable &&
                                                document.unavailable_reason ? (
                                                    <TooltipProvider
                                                        delayDuration={0}
                                                    >
                                                        <Tooltip>
                                                            <TooltipTrigger type="button">
                                                                <Info className="size-3.5 text-muted-foreground" />
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <p>
                                                                    {
                                                                        document.unavailable_reason
                                                                    }
                                                                </p>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </TooltipProvider>
                                                ) : null}
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
                                            <TooltipProvider delayDuration={0}>
                                                <Tooltip>
                                                    <TooltipTrigger
                                                        type="button"
                                                        className="cursor-default"
                                                    >
                                                        <span className="text-xs text-muted-foreground underline decoration-dotted underline-offset-2">
                                                            {missingFieldCount}{' '}
                                                            field
                                                            {missingFieldCount ===
                                                            1
                                                                ? ''
                                                                : 's'}{' '}
                                                            missing
                                                        </span>
                                                    </TooltipTrigger>
                                                    <TooltipContent
                                                        align="end"
                                                        className="max-w-64"
                                                    >
                                                        <ul className="list-disc pl-4 text-left">
                                                            {document.blockers.map(
                                                                (blocker) => (
                                                                    <li
                                                                        key={
                                                                            blocker
                                                                        }
                                                                    >
                                                                        {
                                                                            blocker
                                                                        }
                                                                    </li>
                                                                ),
                                                            )}
                                                        </ul>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        ) : null}
                                        {hasMetadata ? (
                                            <Popover>
                                                <PopoverTrigger asChild>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                    >
                                                        <Info className="size-4" />
                                                        <span className="sr-only">
                                                            Generation details
                                                        </span>
                                                    </Button>
                                                </PopoverTrigger>
                                                <PopoverContent
                                                    align="end"
                                                    className="w-64 text-xs"
                                                >
                                                    <div className="flex flex-col gap-1.5">
                                                        {document.generated_at ? (
                                                            <p>
                                                                Last generated:{' '}
                                                                {formatDateTime(
                                                                    document.generated_at,
                                                                )}
                                                            </p>
                                                        ) : null}
                                                        {hasBeenGenerated ? (
                                                            <p>
                                                                Generated by:{' '}
                                                                {document.generated_by ??
                                                                    '-'}{' '}
                                                                (v
                                                                {
                                                                    document.generated_version
                                                                }
                                                                {document.source_version
                                                                    ? `, source v${document.source_version}`
                                                                    : ''}
                                                                )
                                                            </p>
                                                        ) : null}
                                                        {document.template_version ? (
                                                            <p>
                                                                Template:{' '}
                                                                {
                                                                    document.template_version
                                                                }
                                                            </p>
                                                        ) : null}
                                                    </div>
                                                </PopoverContent>
                                            </Popover>
                                        ) : null}
                                        {document.generated_filename ? (
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        disabled={isPending}
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
                                                            <Eye />
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
                                                            <Printer />
                                                            Print
                                                        </a>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem asChild>
                                                        <a href={downloadHref}>
                                                            <Download />
                                                            Download
                                                        </a>
                                                    </DropdownMenuItem>
                                                    {hasBeenGenerated &&
                                                    canGenerateDocuments &&
                                                    !isLocked ? (
                                                        <DropdownMenuItem
                                                            onSelect={(
                                                                event,
                                                            ) => {
                                                                event.preventDefault();
                                                                setConfirmRegenerateKey(
                                                                    document.key,
                                                                );
                                                            }}
                                                        >
                                                            <RefreshCw />
                                                            Regenerate
                                                        </DropdownMenuItem>
                                                    ) : null}
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        ) : null}
                                    </div>
                                </div>
                                {document.failure_message &&
                                document.status !== 'incomplete' ? (
                                    <p className="pl-6 text-[11px] font-medium text-rose-700 dark:text-rose-200">
                                        {document.failure_message}
                                    </p>
                                ) : null}
                                <Dialog
                                    open={confirmRegenerateKey === document.key}
                                    onOpenChange={(open) => {
                                        if (isPending) {
                                            return;
                                        }

                                        setConfirmRegenerateKey(
                                            open ? document.key : null,
                                        );
                                    }}
                                >
                                    <DialogContent>
                                        <DialogTitle>
                                            Regenerate {document.label}?
                                        </DialogTitle>
                                        <DialogDescription>
                                            This replaces the previously
                                            generated file at the same location.
                                            Anyone who already printed or
                                            downloaded the current version will
                                            need the new copy.
                                        </DialogDescription>
                                        <DialogFooter className="gap-2">
                                            <DialogClose asChild>
                                                <Button
                                                    variant="secondary"
                                                    disabled={isPending}
                                                >
                                                    Cancel
                                                </Button>
                                            </DialogClose>
                                            <Button
                                                disabled={isProcessing}
                                                onClick={async () => {
                                                    setPendingKeys(
                                                        new Set([document.key]),
                                                    );

                                                    try {
                                                        await onRegenerate(
                                                            document.key,
                                                        );
                                                    } finally {
                                                        setPendingKeys(
                                                            new Set(),
                                                        );
                                                        setConfirmRegenerateKey(
                                                            null,
                                                        );
                                                    }
                                                }}
                                            >
                                                {isPending ? (
                                                    <>
                                                        <Spinner />
                                                        Regenerating…
                                                    </>
                                                ) : (
                                                    'Regenerate'
                                                )}
                                            </Button>
                                        </DialogFooter>
                                    </DialogContent>
                                </Dialog>
                            </div>
                        );
                    })}
                </div>
                {packageZipHref ? (
                    <div className="mt-4 border-t border-border/40 pt-4">
                        {allDocumentsGenerated ? (
                            <Button
                                asChild
                                className="h-11 w-full justify-start px-3 shadow-sm"
                            >
                                <a
                                    href={packageZipHref}
                                    className="flex w-full min-w-0 items-center gap-2"
                                >
                                    <Download className="size-4 shrink-0" />
                                    <span className="min-w-0 flex-1 text-left text-sm font-semibold">
                                        Download All as ZIP
                                    </span>
                                </a>
                            </Button>
                        ) : (
                            <p className="rounded-lg border border-dashed border-border/60 bg-muted/10 px-3 py-2 text-xs text-muted-foreground">
                                Generate every applicable document above to
                                enable the ZIP download.
                            </p>
                        )}
                    </div>
                ) : null}
            </CardContent>
        </Card>
    );
};

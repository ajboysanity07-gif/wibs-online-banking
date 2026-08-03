import {
    AlertCircle,
    CheckCircle2,
    Circle,
    ClipboardCheck,
    Clock,
    Info,
    MinusCircle,
    MoreHorizontal,
    PlayCircle,
    RefreshCw,
    XCircle,
} from 'lucide-react';
import { useMemo, useState } from 'react';
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
    onGenerate: (documentKeys: string[]) => void;
};

export const LoanRequestDocumentChecklistCard = ({
    documentChecklist,
    generatedDocumentBaseHref,
    canGenerateDocuments,
    isProcessing,
    onGenerate,
}: LoanRequestDocumentChecklistCardProps) => {
    const [selectedKeys, setSelectedKeys] = useState<Set<string>>(new Set());
    const [hideNotApplicable, setHideNotApplicable] = useState(false);

    const sortedChecklist = useMemo(
        () =>
            [...documentChecklist]
                .filter(
                    (document) => !hideNotApplicable || document.is_applicable,
                )
                .sort((a, b) => {
                    const aIncomplete = a.blockers.length > 0 ? 1 : 0;
                    const bIncomplete = b.blockers.length > 0 ? 1 : 0;

                    return bIncomplete - aIncomplete;
                }),
        [documentChecklist, hideNotApplicable],
    );

    const selectableKeys = sortedChecklist
        .filter((document) => document.is_applicable)
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

    const handleGenerateSelected = () => {
        onGenerate([...selectedKeys]);
        setSelectedKeys(new Set());
    };

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
                            Generate selected
                            {selectedKeys.size > 0
                                ? ` (${selectedKeys.size})`
                                : ''}
                        </Button>
                    ) : null}
                </div>
            </CardHeader>
            <CardContent className="flex flex-col divide-y divide-border/40">
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
                {sortedChecklist.map((document) => {
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
                    const hasMetadata =
                        document.generated_at || hasBeenGenerated;

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
                                            disabled={!document.is_applicable}
                                            onCheckedChange={(checked) =>
                                                toggleKey(
                                                    document.key,
                                                    checked === true,
                                                )
                                            }
                                        />
                                    ) : null}
                                    <StatusIcon
                                        className={cn(
                                            'size-4 shrink-0',
                                            statusIconClassName,
                                        )}
                                    />
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
                                                Witness 2 recorded automatically
                                                at approval if left blank
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
                                                        href={printDocumentHref}
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
                                </div>
                            </div>
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
};

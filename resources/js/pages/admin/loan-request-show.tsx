import { Head, router } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { AdminLoanRequestCorrectionDialog } from '@/components/loan-request/admin-loan-request-correction-dialog';
import { LoanRequestDetailPage } from '@/components/loan-request/loan-request-detail-page';
import {
    Alert,
    AlertDescription,
    AlertTitle,
} from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { useCancelLoanRequest } from '@/hooks/admin/use-cancel-loan-request';
import { useCorrectLoanRequest } from '@/hooks/admin/use-correct-loan-request';
import { useCreateAdminCorrectedLoanRequest } from '@/hooks/admin/use-create-admin-corrected-loan-request';
import { useDismissLoanRequestCorrectionReport } from '@/hooks/admin/use-dismiss-loan-request-correction-report';
import { useUpdateLoanRequestDecision } from '@/hooks/admin/use-update-loan-request-decision';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/formatters';
import {
    index as requestsIndex,
    pdf as requestsPdf,
    print as requestsPrint,
    show as requestsShow,
} from '@/routes/admin/requests';
import type { BreadcrumbItem } from '@/types';
import type {
    LoanRequestCorrectionReport,
    LoanRequestCorrectionPayload,
    LoanRequestDetail,
    LoanRequestPersonData,
    LoanTypeOption,
} from '@/types/loan-requests';

type DecisionState = {
    canDecide: boolean;
    isOwnRequest: boolean;
};

const buildCancellationReasonPrefill = (
    report: LoanRequestCorrectionReport,
): string =>
    `Member reported incorrect details: ${report.issue_description}. Correct information: ${report.correct_information}.`;

const latestOpenReport = (
    reports: LoanRequestCorrectionReport[],
): LoanRequestCorrectionReport | null =>
    reports.find((report) => report.status === 'open') ?? null;

const resolveCancellationReasonPrefill = (
    reports: LoanRequestCorrectionReport[],
    fallback: string | null = null,
): string | null => {
    const openReport = latestOpenReport(reports);

    return openReport ? buildCancellationReasonPrefill(openReport) : fallback;
};

type Props = {
    loanRequest: LoanRequestDetail;
    applicant: LoanRequestPersonData | null;
    coMakerOne: LoanRequestPersonData | null;
    coMakerTwo: LoanRequestPersonData | null;
    decision: DecisionState;
    loanTypes: LoanTypeOption[];
    correctionReports: LoanRequestCorrectionReport[];
    openCorrectionReportCancellationReason: string | null;
};

export default function LoanRequestShow({
    loanRequest,
    applicant,
    coMakerOne,
    coMakerTwo,
    decision,
    loanTypes,
    correctionReports,
    openCorrectionReportCancellationReason,
}: Props) {
    const [currentRequest, setCurrentRequest] =
        useState<LoanRequestDetail>(loanRequest);
    const [currentApplicant, setCurrentApplicant] =
        useState<LoanRequestPersonData | null>(applicant);
    const [currentCoMakerOne, setCurrentCoMakerOne] =
        useState<LoanRequestPersonData | null>(coMakerOne);
    const [currentCoMakerTwo, setCurrentCoMakerTwo] =
        useState<LoanRequestPersonData | null>(coMakerTwo);
    const [currentCorrectionReports, setCurrentCorrectionReports] = useState<
        LoanRequestCorrectionReport[]
    >(correctionReports);
    const [isCorrectionOpen, setIsCorrectionOpen] = useState(false);
    const [isDismissDialogOpen, setIsDismissDialogOpen] = useState(false);
    const [dismissNotes, setDismissNotes] = useState('');
    const [selectedReport, setSelectedReport] =
        useState<LoanRequestCorrectionReport | null>(null);
    const [cancellationReasonPrefill, setCancellationReasonPrefill] = useState<
        string | null
    >(
        resolveCancellationReasonPrefill(
            correctionReports,
            openCorrectionReportCancellationReason,
        ),
    );
    const { updateDecision, processingIds } = useUpdateLoanRequestDecision({
        onUpdated: (updated) => setCurrentRequest(updated),
    });
    const {
        correctLoanRequest,
        processingIds: correctionProcessingIds,
        errors: correctionErrors,
        clearErrors: clearCorrectionErrors,
    } = useCorrectLoanRequest({
        onUpdated: (updated) => {
            setCurrentRequest(updated.loanRequest);
            setCurrentApplicant(updated.applicant);
            setCurrentCoMakerOne(updated.coMakerOne);
            setCurrentCoMakerTwo(updated.coMakerTwo);
            setIsCorrectionOpen(false);
        },
    });
    const {
        cancelLoanRequest,
        processingIds: cancellationProcessingIds,
    } = useCancelLoanRequest({
        onUpdated: (updated) => {
            setCurrentRequest(updated.loanRequest);
            setCurrentCorrectionReports(updated.correctionReports);
            setCancellationReasonPrefill(
                resolveCancellationReasonPrefill(updated.correctionReports),
            );
        },
    });
    const {
        dismissCorrectionReport,
        processingIds: dismissProcessingIds,
    } = useDismissLoanRequestCorrectionReport({
        onDismissed: (result) => {
            setCurrentCorrectionReports(result.correctionReports);
            setCancellationReasonPrefill(
                resolveCancellationReasonPrefill(result.correctionReports),
            );
            setIsDismissDialogOpen(false);
            setDismissNotes('');
            setSelectedReport(null);
        },
    });
    const {
        createAdminCorrectedCopy,
        processingIds: adminCorrectedCopyProcessingIds,
    } = useCreateAdminCorrectedLoanRequest({
        onCreated: (result) => {
            router.visit(result.loanRequest.url);
        },
    });
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Requests', href: requestsIndex().url },
        {
            title: 'Loan request',
            href: requestsShow(currentRequest.id).url,
        },
    ];
    const pdfHref = requestsPdf(currentRequest.id, {
        query: { download: 1 },
    }).url;
    const printHref = requestsPrint(currentRequest.id).url;
    const canDecide =
        currentRequest.status === 'under_review' && decision.canDecide;
    const canCorrect =
        currentRequest.status === 'under_review' && !decision.isOwnRequest;
    const canCreateAdminCorrectedCopy =
        currentRequest.status === 'cancelled' &&
        currentRequest.corrected_request_id === null;
    const blockedMessage =
        currentRequest.status === 'under_review' && decision.isOwnRequest
            ? 'You cannot decide your own loan request.'
            : null;
    const isCorrecting =
        correctionProcessingIds[currentRequest.id] ?? false;
    const isCreatingAdminCorrectedCopy =
        adminCorrectedCopyProcessingIds[currentRequest.id] ?? false;
    const correctedRequestHref =
        currentRequest.corrected_request_id !== null
            ? requestsShow(currentRequest.corrected_request_id).url
            : null;
    const hasCorrectionReports = currentCorrectionReports.length > 0;
    const hasOpenCorrectionReport = currentCorrectionReports.some(
        (report) => report.status === 'open',
    );
    const cancellationDialogEventName = `loan-request-cancel-open-${currentRequest.id}`;
    const statusTone: Record<string, string> = {
        open: 'bg-amber-500/10 text-amber-700 dark:text-amber-200',
        resolved: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-200',
        dismissed: 'bg-rose-500/10 text-rose-700 dark:text-rose-200',
    };

    const handleCorrectionOpenChange = (open: boolean) => {
        if (open) {
            clearCorrectionErrors();
        }

        setIsCorrectionOpen(open);
    };

    const handleCorrectionSubmit = (payload: LoanRequestCorrectionPayload) => {
        void correctLoanRequest(currentRequest.id, payload);
    };

    const openCancellationDialogFromReport = (
        report: LoanRequestCorrectionReport,
    ) => {
        const prefill = buildCancellationReasonPrefill(report);

        setCancellationReasonPrefill(prefill);
        window.dispatchEvent(
            new CustomEvent(cancellationDialogEventName, {
                detail: { prefill },
            }),
        );
    };

    const openDismissDialog = (report: LoanRequestCorrectionReport) => {
        setSelectedReport(report);
        setDismissNotes('');
        setIsDismissDialogOpen(true);
    };

    const submitDismissReport = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (selectedReport === null) {
            return;
        }

        await dismissCorrectionReport(currentRequest.id, selectedReport.id, {
            admin_notes: dismissNotes.trim() || null,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Loan request" />
            {hasCorrectionReports ? (
                <section className="mx-auto mb-6 w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                    <Card className="border-amber-500/25 bg-amber-500/[0.06]">
                        <CardHeader>
                            <div className="flex items-start gap-3">
                                <div className="rounded-full bg-amber-500/10 p-2 text-amber-700 dark:text-amber-200">
                                    <CircleAlert className="size-4" />
                                </div>
                                <div className="space-y-1">
                                    <CardTitle>
                                        Member reported incorrect details
                                    </CardTitle>
                                    <CardDescription>
                                        Review reported issues before
                                        cancelling and creating an
                                        admin-corrected request.
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {currentCorrectionReports.map((report) => {
                                const isOpen = report.status === 'open';
                                const reporterName =
                                    report.reported_by?.name ?? '--';
                                const reporterAcctNo =
                                    report.reported_by?.acctno ?? '--';
                                const reportedAt =
                                    formatDateTime(report.reported_at);
                                const isDismissing =
                                    dismissProcessingIds[report.id] ?? false;

                                return (
                                    <div
                                        key={report.id}
                                        className="rounded-lg border border-border/50 bg-background/80 p-4"
                                    >
                                        <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                                            <Badge
                                                variant="secondary"
                                                className={
                                                    statusTone[report.status]
                                                }
                                            >
                                                {report.status}
                                            </Badge>
                                            <span className="text-xs text-muted-foreground">
                                                Reported at: {reportedAt}
                                            </span>
                                        </div>
                                        <div className="grid gap-3 text-sm md:grid-cols-2">
                                            <div>
                                                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                                    Reported issue
                                                </p>
                                                <p className="mt-1 whitespace-pre-wrap">
                                                    {
                                                        report.issue_description
                                                    }
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                                    Correct information
                                                </p>
                                                <p className="mt-1 whitespace-pre-wrap">
                                                    {
                                                        report.correct_information
                                                    }
                                                </p>
                                            </div>
                                        </div>
                                        <div className="mt-3 grid gap-3 text-sm md:grid-cols-2">
                                            <div>
                                                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                                    Supporting note/proof
                                                </p>
                                                <p className="mt-1 whitespace-pre-wrap">
                                                    {report.supporting_note ??
                                                        '--'}
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                                    Reported by
                                                </p>
                                                <p className="mt-1">
                                                    {reporterName} (Acct:{' '}
                                                    {reporterAcctNo})
                                                </p>
                                            </div>
                                        </div>
                                        {isOpen ? (
                                            <div className="mt-4 flex flex-wrap gap-2">
                                                {currentRequest.status ===
                                                'approved' ? (
                                                    <Button
                                                        type="button"
                                                        variant="destructive"
                                                        onClick={() =>
                                                            openCancellationDialogFromReport(
                                                                report,
                                                            )
                                                        }
                                                    >
                                                        Cancel approved request
                                                    </Button>
                                                ) : null}
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    disabled={isDismissing}
                                                    onClick={() =>
                                                        openDismissDialog(
                                                            report,
                                                        )
                                                    }
                                                >
                                                    Dismiss report
                                                </Button>
                                            </div>
                                        ) : null}
                                    </div>
                                );
                            })}
                        </CardContent>
                    </Card>
                    {hasOpenCorrectionReport && currentRequest.status !== 'approved' ? (
                        <Alert className="mt-3 border-amber-500/30 bg-amber-500/10">
                            <CircleAlert className="size-4 text-amber-700 dark:text-amber-200" />
                            <AlertTitle>Open correction report found</AlertTitle>
                            <AlertDescription>
                                Open reports can be dismissed now. Cancellation
                                is available only while the request is still
                                approved.
                            </AlertDescription>
                        </Alert>
                    ) : null}
                </section>
            ) : null}
            <LoanRequestDetailPage
                loanRequest={currentRequest}
                applicant={currentApplicant}
                coMakerOne={currentCoMakerOne}
                coMakerTwo={currentCoMakerTwo}
                backHref={requestsIndex().url}
                backLabel="Back to requests"
                pdfHref={pdfHref}
                printHref={printHref}
                correctedRequestHref={correctedRequestHref}
                correction={{
                    show: canCorrect,
                    isProcessing: isCorrecting,
                    onEdit: () => handleCorrectionOpenChange(true),
                }}
                correctedCopy={
                    canCreateAdminCorrectedCopy
                        ? {
                              isProcessing: isCreatingAdminCorrectedCopy,
                              buttonLabel: 'Create Admin-Corrected Request',
                              dialogTitle: 'Create Admin-Corrected Request',
                              dialogDescription:
                                  'This will create a new corrected request copied from the cancelled request. The cancelled request will remain read-only for audit history.',
                              onCreate: (payload) =>
                                  createAdminCorrectedCopy(
                                      currentRequest.id,
                                      payload,
                                  ),
                          }
                        : undefined
                }
                decision={{
                    show: true,
                    canDecide,
                    blockedMessage,
                    isProcessing: processingIds[currentRequest.id] ?? false,
                    isCancelling:
                        cancellationProcessingIds[currentRequest.id] ?? false,
                    cancellationReasonPrefill,
                    cancellationDialogEventName,
                    onApprove: (payload) =>
                        updateDecision(currentRequest.id, 'approve', payload),
                    onDecline: (payload) =>
                        updateDecision(currentRequest.id, 'decline', payload),
                    onCancelApproved: (payload) =>
                        cancelLoanRequest(currentRequest.id, payload),
                }}
            />
            <AdminLoanRequestCorrectionDialog
                open={isCorrectionOpen}
                loanRequest={currentRequest}
                applicant={currentApplicant}
                coMakerOne={currentCoMakerOne}
                coMakerTwo={currentCoMakerTwo}
                loanTypes={loanTypes}
                errors={correctionErrors}
                isProcessing={isCorrecting}
                onOpenChange={handleCorrectionOpenChange}
                onSubmit={handleCorrectionSubmit}
            />
            <Dialog
                open={isDismissDialogOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setIsDismissDialogOpen(false);
                        setSelectedReport(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Dismiss correction report</DialogTitle>
                        <DialogDescription>
                            Add optional notes about why this report is being
                            dismissed.
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submitDismissReport}>
                        <div className="space-y-2">
                            <Label htmlFor="dismiss_admin_notes">
                                Admin notes (optional)
                            </Label>
                            <textarea
                                id="dismiss_admin_notes"
                                className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 flex min-h-[112px] w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50"
                                maxLength={2000}
                                value={dismissNotes}
                                disabled={
                                    selectedReport !== null &&
                                    (dismissProcessingIds[selectedReport.id] ??
                                        false)
                                }
                                onChange={(event) =>
                                    setDismissNotes(event.target.value)
                                }
                            />
                            <div className="text-right text-xs text-muted-foreground">
                                {dismissNotes.length}/2000
                            </div>
                        </div>
                        <DialogFooter className="gap-2 sm:gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsDismissDialogOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={
                                    selectedReport === null ||
                                    (selectedReport !== null &&
                                        (dismissProcessingIds[
                                            selectedReport.id
                                        ] ?? false))
                                }
                            >
                                Dismiss report
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

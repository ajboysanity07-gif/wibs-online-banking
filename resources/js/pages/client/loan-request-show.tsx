import { Head } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import InputError from '@/components/input-error';
import { LoanRequestDetailPage } from '@/components/loan-request/loan-request-detail-page';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { useCancelMemberLoanRequest } from '@/hooks/use-cancel-member-loan-request';
import { useSubmitLoanRequestCorrectionReport } from '@/hooks/use-submit-loan-request-correction-report';
import AppLayout from '@/layouts/app-layout';
import { dashboard as clientDashboard } from '@/routes/client';
import {
    approvedDocuments as loanRequestApprovedDocuments,
    index as loanRequestsIndex,
    pdf as loanRequestPdf,
    print as loanRequestPrint,
    show as loanRequestShow,
} from '@/routes/client/loan-requests';
import {
    affidavitUndertaking as loanRequestAffidavitUndertakingDocument,
    applicationForm as loanRequestApplicationFormDocument,
    authorization as loanRequestAuthorizationDocument,
    grepalife as loanRequestGrepalifeDocument,
    loanSecurityAgreement as loanRequestLoanSecurityAgreementDocument,
    planOfPayment as loanRequestPlanOfPaymentDocument,
    undertakingBarangay as loanRequestUndertakingBarangayDocument,
} from '@/routes/client/loan-requests/documents';
import type { BreadcrumbItem } from '@/types';
import type {
    LoanRequestDetail,
    LoanRequestPersonData,
} from '@/types/loan-requests';

type Props = {
    loanRequest: LoanRequestDetail;
    applicant: LoanRequestPersonData | null;
    coMakerOne: LoanRequestPersonData | null;
    coMakerTwo: LoanRequestPersonData | null;
    hasOpenCorrectionReport: boolean;
};

export default function LoanRequestShow({
    loanRequest,
    applicant,
    coMakerOne,
    coMakerTwo,
    hasOpenCorrectionReport,
}: Props) {
    const [currentLoanRequest, setCurrentLoanRequest] =
        useState<LoanRequestDetail>(loanRequest);
    const [isReportDialogOpen, setIsReportDialogOpen] = useState(false);
    const [issueDescription, setIssueDescription] = useState('');
    const [correctInformation, setCorrectInformation] = useState('');
    const [supportingNote, setSupportingNote] = useState('');
    const [issueError, setIssueError] = useState<string | null>(null);
    const [correctError, setCorrectError] = useState<string | null>(null);
    const [hasOpenReportState, setHasOpenReportState] = useState(
        hasOpenCorrectionReport,
    );
    const { cancelLoanRequest, processingIds: cancellationProcessingIds } =
        useCancelMemberLoanRequest({
            onUpdated: (updated) => setCurrentLoanRequest(updated),
        });
    const { submitReport, processingIds } =
        useSubmitLoanRequestCorrectionReport({
            onSubmitted: () => {
                setHasOpenReportState(true);
                setIsReportDialogOpen(false);
                setIssueDescription('');
                setCorrectInformation('');
                setSupportingNote('');
                setIssueError(null);
                setCorrectError(null);
            },
        });
    const isReportSubmitting = processingIds[loanRequest.id] ?? false;
    const isCancellationSubmitting =
        cancellationProcessingIds[currentLoanRequest.id] ?? false;
    const loanRequestsIndexHref = loanRequestsIndex().url;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Overview', href: clientDashboard().url },
        { title: 'Loan Requests', href: loanRequestsIndexHref },
        {
            title: 'Loan request',
            href: loanRequestShow(loanRequest.id).url,
        },
    ];

    const pdfHref = loanRequestPdf(loanRequest.id, {
        query: { download: 1 },
    }).url;
    const printHref = loanRequestPrint(loanRequest.id).url;
    const approvedDocumentHrefs =
        currentLoanRequest.status === 'approved'
            ? {
                  applicationForm: loanRequestApplicationFormDocument(
                      currentLoanRequest.id,
                  ).url,
                  grepalife: loanRequestGrepalifeDocument(currentLoanRequest.id)
                      .url,
                  loanSecurityAgreement:
                      loanRequestLoanSecurityAgreementDocument(
                          currentLoanRequest.id,
                      ).url,
                  planOfPayment: loanRequestPlanOfPaymentDocument(
                      currentLoanRequest.id,
                  ).url,
                  undertakingBarangay: loanRequestUndertakingBarangayDocument(
                      currentLoanRequest.id,
                  ).url,
                  affidavitUndertaking: loanRequestAffidavitUndertakingDocument(
                      currentLoanRequest.id,
                  ).url,
                  authorization: loanRequestAuthorizationDocument(
                      currentLoanRequest.id,
                  ).url,
                  packageZip: loanRequestApprovedDocuments(
                      currentLoanRequest.id,
                  ).url,
              }
            : null;
    const correctedRequestHref =
        currentLoanRequest.corrected_request_id !== null
            ? loanRequestShow(currentLoanRequest.corrected_request_id).url
            : null;
    const canCancelApplication =
        currentLoanRequest.status !== null &&
        ['submitted', 'pending_review', 'under_review'].includes(
            currentLoanRequest.status,
        );

    const submitCorrectionReport = async (
        event: FormEvent<HTMLFormElement>,
    ) => {
        event.preventDefault();

        const issueValue = issueDescription.trim();
        const correctValue = correctInformation.trim();

        if (issueValue === '') {
            setIssueError('This field is required.');
            return;
        }

        if (correctValue === '') {
            setCorrectError('This field is required.');
            return;
        }

        setIssueError(null);
        setCorrectError(null);

        await submitReport(loanRequest.id, {
            issue_description: issueValue,
            correct_information: correctValue,
            supporting_note: supportingNote.trim() || null,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Loan request" />
            {currentLoanRequest.status === 'approved' ? (
                <section className="mx-auto mb-6 w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                    {hasOpenReportState ? (
                        <Alert className="border-amber-500/30 bg-amber-500/10">
                            <CircleAlert className="size-4 text-amber-700 dark:text-amber-200" />
                            <AlertTitle>Correction report pending</AlertTitle>
                            <AlertDescription>
                                An admin will review your reported correction.
                            </AlertDescription>
                        </Alert>
                    ) : (
                        <div className="rounded-xl border border-border/40 bg-card/70 p-4">
                            <p className="text-sm text-muted-foreground">
                                Found incorrect details in this approved
                                request?
                            </p>
                            <Button
                                type="button"
                                className="mt-3"
                                onClick={() => setIsReportDialogOpen(true)}
                            >
                                Report incorrect details
                            </Button>
                        </div>
                    )}
                </section>
            ) : null}
            <LoanRequestDetailPage
                loanRequest={currentLoanRequest}
                applicant={applicant}
                coMakerOne={coMakerOne}
                coMakerTwo={coMakerTwo}
                backHref={loanRequestsIndexHref}
                backLabel="Back to loan requests"
                pdfHref={pdfHref}
                printHref={printHref}
                approvedDocumentHrefs={approvedDocumentHrefs}
                correctedRequestHref={correctedRequestHref}
                cancellation={{
                    show: canCancelApplication,
                    isProcessing: isCancellationSubmitting,
                    reasonRequired: false,
                    actionLabel: 'Cancel Application',
                    dialogTitle: 'Cancel Application',
                    dialogDescription:
                        'This will cancel your application before a final decision is made.',
                    confirmLabel: 'Confirm Cancellation',
                    dismissLabel: 'Keep Application',
                    reasonLabel: 'Reason (optional)',
                    onSubmit: (payload) =>
                        cancelLoanRequest(currentLoanRequest.id, payload),
                }}
            />
            <Dialog
                open={isReportDialogOpen}
                onOpenChange={(open) => {
                    if (isReportSubmitting && !open) {
                        return;
                    }

                    setIsReportDialogOpen(open);
                }}
            >
                <DialogContent className="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Report incorrect details</DialogTitle>
                        <DialogDescription>
                            Tell us what information is wrong. An admin will
                            review your report. If the approved request needs
                            correction, it may be cancelled and replaced with a
                            corrected request.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        className="space-y-4"
                        onSubmit={submitCorrectionReport}
                    >
                        <div className="space-y-2">
                            <Label htmlFor="issue_description">
                                What information is wrong?
                            </Label>
                            <textarea
                                id="issue_description"
                                className="flex min-h-[112px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50"
                                maxLength={2000}
                                required
                                value={issueDescription}
                                disabled={isReportSubmitting}
                                onChange={(event) => {
                                    setIssueDescription(event.target.value);
                                    setIssueError(null);
                                }}
                            />
                            <div className="flex items-start justify-between gap-3">
                                <InputError message={issueError ?? ''} />
                                <span className="ml-auto text-xs text-muted-foreground">
                                    {issueDescription.length}/2000
                                </span>
                            </div>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="correct_information">
                                Correct information
                            </Label>
                            <textarea
                                id="correct_information"
                                className="flex min-h-[112px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50"
                                maxLength={2000}
                                required
                                value={correctInformation}
                                disabled={isReportSubmitting}
                                onChange={(event) => {
                                    setCorrectInformation(event.target.value);
                                    setCorrectError(null);
                                }}
                            />
                            <div className="flex items-start justify-between gap-3">
                                <InputError message={correctError ?? ''} />
                                <span className="ml-auto text-xs text-muted-foreground">
                                    {correctInformation.length}/2000
                                </span>
                            </div>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="supporting_note">
                                Supporting note or proof
                            </Label>
                            <textarea
                                id="supporting_note"
                                className="flex min-h-[96px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50"
                                maxLength={2000}
                                value={supportingNote}
                                disabled={isReportSubmitting}
                                onChange={(event) =>
                                    setSupportingNote(event.target.value)
                                }
                            />
                            <div className="text-right text-xs text-muted-foreground">
                                {supportingNote.length}/2000
                            </div>
                        </div>
                        <DialogFooter className="gap-2 sm:gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={isReportSubmitting}
                                onClick={() => setIsReportDialogOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={isReportSubmitting}>
                                Send report
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

import { Head, Link } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import InputError from '@/components/input-error';
import { LoanRequestDetailPage } from '@/components/loan-request/loan-request-detail-page';
import { LoanRequestStatusBadge } from '@/components/loan-request/loan-request-status-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useCancelMemberLoanRequest } from '@/hooks/use-cancel-member-loan-request';
import { useResolveMemberLoanRequestAction } from '@/hooks/use-resolve-member-loan-request-action';
import { useSubmitLoanRequestCorrectionReport } from '@/hooks/use-submit-loan-request-correction-report';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/formatters';
import { dashboard as clientDashboard } from '@/routes/client';
import {
    approvedDocuments as loanRequestApprovedDocuments,
    index as loanRequestsIndex,
    pdf as loanRequestPdf,
    show as loanRequestShow,
} from '@/routes/client/loan-requests';
import {
    affidavitUndertaking as loanRequestAffidavitUndertakingDocument,
    applicationForm as loanRequestApplicationFormDocument,
    authorityToDeduct as loanRequestAuthorityToDeductDocument,
    depedSalaryDeductionWaiver as loanRequestDepedSalaryDeductionWaiverDocument,
    disclosureStatement as loanRequestDisclosureStatementDocument,
    generali as loanRequestGeneraliDocument,
    generaliApplicationForm as loanRequestGeneraliApplicationFormDocument,
    grepalife as loanRequestGrepalifeDocument,
    loanInformation as loanRequestLoanInformationDocument,
    loanSecurityAgreement as loanRequestLoanSecurityAgreementDocument,
    pensionDeductionWaiver as loanRequestPensionDeductionWaiverDocument,
    planOfPayment as loanRequestPlanOfPaymentDocument,
    promissoryNote as loanRequestPromissoryNoteDocument,
    undertakingBarangay as loanRequestUndertakingBarangayDocument,
} from '@/routes/client/loan-requests/documents';
import type { BreadcrumbItem } from '@/types';
import type {
    LoanRequestAuditEntry,
    LoanRequestDataFieldDefinition,
    LoanRequestDataSectionDefinitions,
    LoanRequestDataSections,
    LoanRequestDetail,
    LoanRequestPersonData,
} from '@/types/loan-requests';

type Props = {
    loanRequest: LoanRequestDetail;
    applicant: LoanRequestPersonData | null;
    coMakerOne: LoanRequestPersonData | null;
    coMakerTwo: LoanRequestPersonData | null;
    auditTrail: LoanRequestAuditEntry[];
    dataSections: LoanRequestDataSections;
    dataSectionDefinitions: LoanRequestDataSectionDefinitions;
    hasOpenCorrectionReport: boolean;
};

const textareaClassName =
    'flex min-h-[112px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50';

const booleanSelectValue = (value: unknown): string => {
    if (value === true) {
        return 'Yes';
    }

    if (value === false) {
        return 'No';
    }

    return '';
};

const termsSummaryValue = (
    value: string | number | null | undefined,
): string =>
    value === null || value === undefined || `${value}`.trim() === ''
        ? '--'
        : `${value}`;

export default function LoanRequestShow({
    loanRequest,
    applicant,
    coMakerOne,
    coMakerTwo,
    auditTrail,
    dataSections,
    dataSectionDefinitions,
    hasOpenCorrectionReport,
}: Props) {
    const [currentLoanRequest, setCurrentLoanRequest] =
        useState<LoanRequestDetail>(loanRequest);
    const [currentAuditTrail, setCurrentAuditTrail] =
        useState<LoanRequestAuditEntry[]>(auditTrail);
    const [currentDataSections, setCurrentDataSections] =
        useState<LoanRequestDataSections>(dataSections);
    const [isReportDialogOpen, setIsReportDialogOpen] = useState(false);
    const [issueDescription, setIssueDescription] = useState('');
    const [correctInformation, setCorrectInformation] = useState('');
    const [supportingNote, setSupportingNote] = useState('');
    const [issueError, setIssueError] = useState<string | null>(null);
    const [correctError, setCorrectError] = useState<string | null>(null);
    const [memberActionReason, setMemberActionReason] = useState('');
    const [hasOpenReportState, setHasOpenReportState] = useState(
        hasOpenCorrectionReport,
    );
    const { cancelLoanRequest, processingIds: cancellationProcessingIds } =
        useCancelMemberLoanRequest({
            onUpdated: (result) => {
                setCurrentLoanRequest(result.loanRequest);
                setCurrentAuditTrail(result.auditTrail);
            },
        });
    const { resolveAction, processingIds: actionProcessingIds } =
        useResolveMemberLoanRequestAction({
            onUpdated: (result) => {
                setCurrentLoanRequest(result.loanRequest);
                setCurrentAuditTrail(result.auditTrail);
                setCurrentDataSections(result.dataSections);
            },
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
    const isMemberActionSubmitting =
        actionProcessingIds[currentLoanRequest.id] ?? false;
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
    const approvedDocumentHrefs =
        currentLoanRequest.status === 'approved' ||
        currentLoanRequest.status === 'converted_to_loan'
            ? {
                  applicationForm: loanRequestApplicationFormDocument(
                      currentLoanRequest.id,
                  ).url,
                  grepalife: loanRequestGrepalifeDocument(currentLoanRequest.id)
                      .url,
                  affidavitUndertaking: loanRequestAffidavitUndertakingDocument(
                      currentLoanRequest.id,
                  ).url,
                  loanInformation: loanRequestLoanInformationDocument(
                      currentLoanRequest.id,
                  ).url,
                  planOfPayment: loanRequestPlanOfPaymentDocument(
                      currentLoanRequest.id,
                  ).url,
                  disclosureStatement: loanRequestDisclosureStatementDocument(
                      currentLoanRequest.id,
                  ).url,
                  promissoryNote: loanRequestPromissoryNoteDocument(
                      currentLoanRequest.id,
                  ).url,
                  undertakingBarangay: loanRequestUndertakingBarangayDocument(
                      currentLoanRequest.id,
                  ).url,
                  loanSecurityAgreement:
                      loanRequestLoanSecurityAgreementDocument(
                          currentLoanRequest.id,
                      ).url,
                  generali: loanRequestGeneraliDocument(currentLoanRequest.id)
                      .url,
                  authorityToDeduct: loanRequestAuthorityToDeductDocument(
                      currentLoanRequest.id,
                  ).url,
                  depedSalaryDeductionWaiver:
                      loanRequestDepedSalaryDeductionWaiverDocument(
                          currentLoanRequest.id,
                      ).url,
                  pensionDeductionWaiver:
                      loanRequestPensionDeductionWaiverDocument(
                          currentLoanRequest.id,
                      ).url,
                  generaliApplicationForm:
                      loanRequestGeneraliApplicationFormDocument(
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
    const fieldDirectory = new Map<
        string,
        {
            sectionKey: keyof LoanRequestDataSections;
            definition: LoanRequestDataFieldDefinition;
        }
    >();

    Object.entries(dataSectionDefinitions).forEach(
        ([sectionKey, sectionDefinition]) => {
            Object.entries(sectionDefinition.fields).forEach(
                ([fieldKey, definition]) => {
                    fieldDirectory.set(fieldKey, {
                        sectionKey: sectionKey as keyof LoanRequestDataSections,
                        definition,
                    });
                },
            );
        },
    );
    const requestedFieldKeys = currentLoanRequest.member_action_fields ?? [];
    const memberActionFields = requestedFieldKeys
        .map((fieldKey) => {
            const resolved = fieldDirectory.get(fieldKey);

            if (!resolved) {
                return null;
            }

            return {
                key: fieldKey,
                sectionKey: resolved.sectionKey,
                definition: resolved.definition,
                value:
                    currentDataSections[resolved.sectionKey]?.[fieldKey] ??
                    null,
            };
        })
        .filter(
            (
                field,
            ): field is {
                key: string;
                sectionKey: keyof LoanRequestDataSections;
                definition: LoanRequestDataFieldDefinition;
                value: string | number | boolean | null;
            } => field !== null,
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

    const updateMemberActionField = (
        sectionKey: keyof LoanRequestDataSections,
        fieldKey: string,
        value: string | number | boolean | null,
    ) => {
        setCurrentDataSections((current) => ({
            ...current,
            [sectionKey]: {
                ...current[sectionKey],
                [fieldKey]: value,
            },
        }));
    };

    const submitMemberInformation = async (
        event: FormEvent<HTMLFormElement>,
    ) => {
        event.preventDefault();

        await resolveAction(
            currentLoanRequest.id,
            {
                insurance: currentDataSections.insurance,
                health: currentDataSections.health,
                banking: currentDataSections.banking,
                barangay: currentDataSections.barangay,
                declarations: currentDataSections.declarations,
            },
            'Requested information submitted successfully.',
        );
    };

    const submitTermsDecision = async (decision: 'accept' | 'decline') => {
        const result = await resolveAction(
            currentLoanRequest.id,
            {
                decision,
                reason: memberActionReason.trim() || null,
            },
            decision === 'accept'
                ? 'Revised loan terms accepted.'
                : 'Revised loan terms declined.',
        );

        if (result) {
            setMemberActionReason('');
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Loan request" />
            <section className="mx-auto mb-6 w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="rounded-xl border border-border/40 bg-card/70 p-5">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div className="space-y-1">
                            <p className="text-sm font-semibold text-foreground">
                                Your application has been received
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Request {currentLoanRequest.reference} is
                                currently under review by our team. We'll notify
                                you once a decision has been made.
                            </p>
                        </div>
                        <LoanRequestStatusBadge
                            status={currentLoanRequest.status}
                            className="text-xs"
                        />
                    </div>
                    {currentLoanRequest.submitted_at ? (
                        <p className="mt-3 text-xs text-muted-foreground">
                            Submitted{' '}
                            {formatDate(currentLoanRequest.submitted_at)}
                        </p>
                    ) : null}
                </div>
            </section>
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
            {currentLoanRequest.status === 'awaiting_member_information' ? (
                <section className="mx-auto mb-6 w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                    <Card className="border-amber-500/30 bg-card/80">
                        <CardHeader>
                            <CardTitle>Awaiting member information</CardTitle>
                            <CardDescription>
                                {currentLoanRequest.member_action_message ??
                                    'Please complete the requested fields so processing can continue.'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form
                                className="space-y-5"
                                onSubmit={submitMemberInformation}
                            >
                                {memberActionFields.length > 0 ? (
                                    <div className="grid gap-4 md:grid-cols-2">
                                        {memberActionFields.map((field) => (
                                            <div
                                                key={field.key}
                                                className={
                                                    field.definition.type ===
                                                        'string' &&
                                                    field.key.includes('notes')
                                                        ? 'grid gap-2 md:col-span-2'
                                                        : 'grid gap-2'
                                                }
                                            >
                                                <Label
                                                    htmlFor={`member_action_${field.key}`}
                                                >
                                                    {field.definition.label}
                                                </Label>
                                                {field.definition.type ===
                                                'boolean' ? (
                                                    <Select
                                                        value={booleanSelectValue(
                                                            field.value,
                                                        )}
                                                        onValueChange={(
                                                            nextValue,
                                                        ) =>
                                                            updateMemberActionField(
                                                                field.sectionKey,
                                                                field.key,
                                                                nextValue ===
                                                                    'Yes',
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger
                                                            id={`member_action_${field.key}`}
                                                        >
                                                            <SelectValue placeholder="Select an option" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="Yes">
                                                                Yes
                                                            </SelectItem>
                                                            <SelectItem value="No">
                                                                No
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                ) : field.key.includes(
                                                      'notes',
                                                  ) ? (
                                                    <textarea
                                                        id={`member_action_${field.key}`}
                                                        className={
                                                            textareaClassName
                                                        }
                                                        value={
                                                            field.value
                                                                ? `${field.value}`
                                                                : ''
                                                        }
                                                        onChange={(event) =>
                                                            updateMemberActionField(
                                                                field.sectionKey,
                                                                field.key,
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                    />
                                                ) : (
                                                    <Input
                                                        id={`member_action_${field.key}`}
                                                        type={
                                                            field.definition
                                                                .type ===
                                                                'number' ||
                                                            field.definition
                                                                .type ===
                                                                'integer'
                                                                ? 'number'
                                                                : 'text'
                                                        }
                                                        value={
                                                            field.value
                                                                ? `${field.value}`
                                                                : ''
                                                        }
                                                        onChange={(event) =>
                                                            updateMemberActionField(
                                                                field.sectionKey,
                                                                field.key,
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                    />
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <Alert>
                                        <AlertTitle>
                                            Requested information
                                        </AlertTitle>
                                        <AlertDescription>
                                            This request is waiting for your
                                            updated confirmation. Review the
                                            note above and submit once you have
                                            completed the required details.
                                        </AlertDescription>
                                    </Alert>
                                )}
                                <div className="flex flex-wrap items-center gap-3">
                                    <Button
                                        type="submit"
                                        disabled={isMemberActionSubmitting}
                                    >
                                        Submit requested information
                                    </Button>
                                    <Button
                                        asChild
                                        variant="outline"
                                        disabled={isMemberActionSubmitting}
                                    >
                                        <Link
                                            href={
                                                loanRequestShow(
                                                    currentLoanRequest.id,
                                                ).url
                                            }
                                        >
                                            Refresh request
                                        </Link>
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </section>
            ) : null}
            {currentLoanRequest.status === 'awaiting_member_acceptance' ? (
                <section className="mx-auto mb-6 w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                    <Card className="border-indigo-500/30 bg-card/80">
                        <CardHeader>
                            <CardTitle>Awaiting member acceptance</CardTitle>
                            <CardDescription>
                                {currentLoanRequest.member_action_message ??
                                    'Review the revised loan terms before continuing.'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="rounded-lg border border-border/50 bg-muted/20 p-4">
                                    <p className="text-xs text-muted-foreground">
                                        Revised amount
                                    </p>
                                    <p className="mt-1 text-sm font-semibold">
                                        {termsSummaryValue(
                                            currentLoanRequest.recommended_amount,
                                        )}
                                    </p>
                                </div>
                                <div className="rounded-lg border border-border/50 bg-muted/20 p-4">
                                    <p className="text-xs text-muted-foreground">
                                        Revised term
                                    </p>
                                    <p className="mt-1 text-sm font-semibold">
                                        {termsSummaryValue(
                                            currentLoanRequest.recommended_term,
                                        )}{' '}
                                        months
                                    </p>
                                </div>
                                <div className="rounded-lg border border-border/50 bg-muted/20 p-4">
                                    <p className="text-xs text-muted-foreground">
                                        Revised interest rate
                                    </p>
                                    <p className="mt-1 text-sm font-semibold">
                                        {termsSummaryValue(
                                            currentLoanRequest.recommended_interest_rate,
                                        )}
                                    </p>
                                </div>
                                <div className="rounded-lg border border-border/50 bg-muted/20 p-4">
                                    <p className="text-xs text-muted-foreground">
                                        Payment frequency
                                    </p>
                                    <p className="mt-1 text-sm font-semibold">
                                        {termsSummaryValue(
                                            currentLoanRequest.recommended_payment_frequency,
                                        )}
                                    </p>
                                </div>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="member_action_reason">
                                    Optional note
                                </Label>
                                <textarea
                                    id="member_action_reason"
                                    className={textareaClassName}
                                    maxLength={1000}
                                    value={memberActionReason}
                                    onChange={(event) =>
                                        setMemberActionReason(
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="flex flex-wrap items-center gap-3">
                                <Button
                                    type="button"
                                    disabled={isMemberActionSubmitting}
                                    onClick={() =>
                                        submitTermsDecision('accept')
                                    }
                                >
                                    Accept revised terms
                                </Button>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    disabled={isMemberActionSubmitting}
                                    onClick={() =>
                                        submitTermsDecision('decline')
                                    }
                                >
                                    Decline revised terms
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </section>
            ) : null}
            {[
                'for_wibs_encoding',
                'wibs_loan_created',
                'release_scheduled',
                'released',
            ].includes(currentLoanRequest.status ?? '') ? (
                <section className="mx-auto mb-6 w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                    <Card>
                        <CardHeader>
                            <CardTitle>Loan Release Status</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm text-muted-foreground">
                                {currentLoanRequest.status ===
                                'for_wibs_encoding'
                                    ? 'Your loan is currently being processed for release in the WIBS system.'
                                    : currentLoanRequest.status ===
                                        'wibs_loan_created'
                                      ? 'Your loan has been created in WIBS. A release date will be scheduled soon.'
                                      : currentLoanRequest.status ===
                                          'release_scheduled'
                                        ? `Your loan release has been scheduled${currentLoanRequest.wibs_release_date ? ' for ' + currentLoanRequest.wibs_release_date : ''}. Please coordinate with your branch.`
                                        : 'Your loan has been released. Please coordinate with your branch for the next steps.'}
                            </p>
                        </CardContent>
                    </Card>
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
                approvedDocumentHrefs={approvedDocumentHrefs}
                correctedRequestHref={correctedRequestHref}
                auditTrail={currentAuditTrail}
                auditTrailAudience="member"
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

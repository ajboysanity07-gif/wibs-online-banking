import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { AdminLoanRequestCorrectionDialog } from '@/components/loan-request/admin-loan-request-correction-dialog';
import { LoanRequestDetailPage } from '@/components/loan-request/loan-request-detail-page';
import { useCancelLoanRequest } from '@/hooks/admin/use-cancel-loan-request';
import { useCorrectLoanRequest } from '@/hooks/admin/use-correct-loan-request';
import { useCreateAdminCorrectedLoanRequest } from '@/hooks/admin/use-create-admin-corrected-loan-request';
import { useUpdateLoanRequestDecision } from '@/hooks/admin/use-update-loan-request-decision';
import AppLayout from '@/layouts/app-layout';
import {
    index as requestsIndex,
    pdf as requestsPdf,
    print as requestsPrint,
    show as requestsShow,
} from '@/routes/admin/requests';
import type { BreadcrumbItem } from '@/types';
import type {
    LoanRequestCorrectionPayload,
    LoanRequestDetail,
    LoanRequestPersonData,
    LoanTypeOption,
} from '@/types/loan-requests';

type DecisionState = {
    canDecide: boolean;
    isOwnRequest: boolean;
};

type Props = {
    loanRequest: LoanRequestDetail;
    applicant: LoanRequestPersonData | null;
    coMakerOne: LoanRequestPersonData | null;
    coMakerTwo: LoanRequestPersonData | null;
    decision: DecisionState;
    loanTypes: LoanTypeOption[];
};

export default function LoanRequestShow({
    loanRequest,
    applicant,
    coMakerOne,
    coMakerTwo,
    decision,
    loanTypes,
}: Props) {
    const [currentRequest, setCurrentRequest] =
        useState<LoanRequestDetail>(loanRequest);
    const [currentApplicant, setCurrentApplicant] =
        useState<LoanRequestPersonData | null>(applicant);
    const [currentCoMakerOne, setCurrentCoMakerOne] =
        useState<LoanRequestPersonData | null>(coMakerOne);
    const [currentCoMakerTwo, setCurrentCoMakerTwo] =
        useState<LoanRequestPersonData | null>(coMakerTwo);
    const [isCorrectionOpen, setIsCorrectionOpen] = useState(false);
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
        onUpdated: (updated) => setCurrentRequest(updated),
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

    const handleCorrectionOpenChange = (open: boolean) => {
        if (open) {
            clearCorrectionErrors();
        }

        setIsCorrectionOpen(open);
    };

    const handleCorrectionSubmit = (payload: LoanRequestCorrectionPayload) => {
        void correctLoanRequest(currentRequest.id, payload);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Loan request" />
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
        </AppLayout>
    );
}

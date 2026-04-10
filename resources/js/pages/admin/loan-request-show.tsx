import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { LoanRequestDetailPage } from '@/components/loan-request/loan-request-detail-page';
import { useUpdateLoanRequestDecision } from '@/hooks/admin/use-update-loan-request-decision';
import AppLayout from '@/layouts/app-layout';
import {
    index as requestsIndex,
    pdf as requestsPdf,
    print as requestsPrint,
    show as requestsShow,
} from '@/routes/admin/requests';
import type { BreadcrumbItem } from '@/types';
import type { LoanRequestDetail, LoanRequestPersonData } from '@/types/loan-requests';

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
};

export default function LoanRequestShow({
    loanRequest,
    applicant,
    coMakerOne,
    coMakerTwo,
    decision,
}: Props) {
    const [currentRequest, setCurrentRequest] =
        useState<LoanRequestDetail>(loanRequest);
    const { updateDecision, processingIds } = useUpdateLoanRequestDecision({
        onUpdated: (updated) => setCurrentRequest(updated),
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
    const blockedMessage =
        currentRequest.status === 'under_review' && decision.isOwnRequest
            ? 'You cannot decide your own loan request.'
            : null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Loan request" />
            <LoanRequestDetailPage
                loanRequest={currentRequest}
                applicant={applicant}
                coMakerOne={coMakerOne}
                coMakerTwo={coMakerTwo}
                backHref={requestsIndex().url}
                backLabel="Back to requests"
                pdfHref={pdfHref}
                printHref={printHref}
                decision={{
                    show: true,
                    canDecide,
                    blockedMessage,
                    isProcessing: processingIds[currentRequest.id] ?? false,
                    onApprove: (payload) =>
                        updateDecision(currentRequest.id, 'approve', payload),
                    onDecline: (payload) =>
                        updateDecision(currentRequest.id, 'decline', payload),
                }}
            />
        </AppLayout>
    );
}

import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { LoanRequestDetailPage } from '@/components/loan-request/loan-request-detail-page';
import AppLayout from '@/layouts/app-layout';
import { showErrorToast, showSuccessToast } from '@/lib/toast';
import { loans as clientLoans } from '@/routes/client';
import {
    pdf as loanRequestPdf,
    print as loanRequestPrint,
    show as loanRequestShow,
} from '@/routes/client/loan-requests';
import type { BreadcrumbItem } from '@/types';
import type { LoanRequestDetail, LoanRequestPersonData } from '@/types/loan-requests';

type Props = {
    loanRequest: LoanRequestDetail;
    applicant: LoanRequestPersonData | null;
    coMakerOne: LoanRequestPersonData | null;
    coMakerTwo: LoanRequestPersonData | null;
};

export default function LoanRequestShow({
    loanRequest,
    applicant,
    coMakerOne,
    coMakerTwo,
}: Props) {
    const [isCreatingCorrectedDraft, setIsCreatingCorrectedDraft] =
        useState(false);
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Loans', href: clientLoans().url },
        {
            title: 'Loan request',
            href: loanRequestShow(loanRequest.id).url,
        },
    ];

    const pdfHref = loanRequestPdf(loanRequest.id, {
        query: { download: 1 },
    }).url;
    const printHref = loanRequestPrint(loanRequest.id).url;
    const correctedCopyUrl = `${loanRequestShow(loanRequest.id).url}/corrected-copy`;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Loan request" />
            <LoanRequestDetailPage
                loanRequest={loanRequest}
                applicant={applicant}
                coMakerOne={coMakerOne}
                coMakerTwo={coMakerTwo}
                backHref={clientLoans().url}
                backLabel="Back to loans"
                pdfHref={pdfHref}
                printHref={printHref}
                correctedCopy={{
                    isProcessing: isCreatingCorrectedDraft,
                    onCreate: () => {
                        if (isCreatingCorrectedDraft) {
                            return;
                        }

                        setIsCreatingCorrectedDraft(true);

                        router.post(
                            correctedCopyUrl,
                            {},
                            {
                                preserveScroll: true,
                                onSuccess: () => {
                                    showSuccessToast(
                                        'Corrected draft created successfully.',
                                        {
                                            id: 'loan-request-corrected-copy',
                                        },
                                    );
                                },
                                onError: (errors) => {
                                    showErrorToast(
                                        errors,
                                        'Failed to create corrected draft.',
                                        {
                                            id: 'loan-request-corrected-copy',
                                        },
                                    );
                                },
                                onFinish: () =>
                                    setIsCreatingCorrectedDraft(false),
                            },
                        );
                    },
                }}
            />
        </AppLayout>
    );
}

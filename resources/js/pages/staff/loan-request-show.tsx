import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { LoanRequestDetailPage } from '@/components/loan-request/loan-request-detail-page';
import { useLoanRequestWorkflow } from '@/hooks/admin/use-loan-request-workflow';
import AppLayout from '@/layouts/app-layout';
import {
    approvedDocuments as requestsApprovedDocuments,
    index as requestsIndex,
    pdf as requestsPdf,
    print as requestsPrint,
    show as requestsShow,
} from '@/routes/staff/loan-requests';
import {
    affidavitUndertaking as requestsAffidavitUndertakingDocument,
    applicationForm as requestsApplicationFormDocument,
    authorization as requestsAuthorizationDocument,
    grepalife as requestsGrepalifeDocument,
    loanSecurityAgreement as requestsLoanSecurityAgreementDocument,
    planOfPayment as requestsPlanOfPaymentDocument,
    undertakingBarangay as requestsUndertakingBarangayDocument,
} from '@/routes/staff/loan-requests/documents';
import type { BreadcrumbItem } from '@/types';
import type {
    LoanRequestDetail,
    LoanRequestPersonData,
    LoanRequestWorkflowContext,
    LoanRequestWorkflowPermission,
} from '@/types/loan-requests';

type Props = {
    loanRequest: LoanRequestDetail;
    applicant: LoanRequestPersonData | null;
    coMakerOne: LoanRequestPersonData | null;
    coMakerTwo: LoanRequestPersonData | null;
    workflowPermissions: LoanRequestWorkflowPermission[];
    workflowContext: LoanRequestWorkflowContext;
};

export default function StaffLoanRequestShow({
    loanRequest,
    applicant,
    coMakerOne,
    coMakerTwo,
    workflowPermissions,
    workflowContext,
}: Props) {
    const [currentRequest, setCurrentRequest] =
        useState<LoanRequestDetail>(loanRequest);
    const [currentApplicant, setCurrentApplicant] =
        useState<LoanRequestPersonData | null>(applicant);
    const [currentCoMakerOne, setCurrentCoMakerOne] =
        useState<LoanRequestPersonData | null>(coMakerOne);
    const [currentCoMakerTwo, setCurrentCoMakerTwo] =
        useState<LoanRequestPersonData | null>(coMakerTwo);
    const { 
        startReview,
        requestRevision,
        rejectLoanRequest,
        recommendApproval,
        approveLoanRequest,
        declineLoanRequest,
        convertToLoan,
        processingIds: workflowProcessingIds,
    } = useLoanRequestWorkflow({
        onUpdated: (result) => {
            setCurrentRequest(result.loanRequest);
            setCurrentApplicant(result.applicant);
            setCurrentCoMakerOne(result.coMakerOne);
            setCurrentCoMakerTwo(result.coMakerTwo);
        },
    });
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Loan Workflow',
            href: requestsIndex().url,
        },
        {
            title: 'Loan request',
            href: requestsShow(currentRequest.id).url,
        },
    ];
    const pdfHref = requestsPdf(currentRequest.id, {
        query: { download: 1 },
    }).url;
    const printHref = requestsPrint(currentRequest.id).url;
    const approvedDocumentHrefs =
        currentRequest.status === 'approved' ||
        currentRequest.status === 'converted_to_loan'
            ? {
                  applicationForm: requestsApplicationFormDocument(
                      currentRequest.id,
                  ).url,
                  grepalife: requestsGrepalifeDocument(currentRequest.id).url,
                  loanSecurityAgreement: requestsLoanSecurityAgreementDocument(
                      currentRequest.id,
                  ).url,
                  planOfPayment: requestsPlanOfPaymentDocument(
                      currentRequest.id,
                  ).url,
                  undertakingBarangay: requestsUndertakingBarangayDocument(
                      currentRequest.id,
                  ).url,
                  affidavitUndertaking: requestsAffidavitUndertakingDocument(
                      currentRequest.id,
                  ).url,
                  authorization: requestsAuthorizationDocument(
                      currentRequest.id,
                  ).url,
                  packageZip: requestsApprovedDocuments(currentRequest.id).url,
              }
            : null;
    const hasWorkflowPermission = (
        permission: LoanRequestWorkflowPermission,
    ): boolean => workflowPermissions.includes(permission);
    const isOwnRequest = workflowContext.isOwnRequest;
    const canStartReview =
        !isOwnRequest &&
        currentRequest.status === 'pending_review' &&
        hasWorkflowPermission('loan.review');
    const canRequestRevision =
        !isOwnRequest &&
        (currentRequest.status === 'pending_review' ||
            currentRequest.status === 'under_review') &&
        hasWorkflowPermission('loan.request_revision');
    const canReject =
        !isOwnRequest &&
        (currentRequest.status === 'pending_review' ||
            currentRequest.status === 'under_review') &&
        hasWorkflowPermission('loan.reject');
    const canRecommendApproval =
        !isOwnRequest &&
        currentRequest.status === 'under_review' &&
        hasWorkflowPermission('loan.recommend_approval');
    const canWorkflowApprove =
        !isOwnRequest &&
        currentRequest.status === 'recommended_for_approval' &&
        hasWorkflowPermission('loan.approve');
    const canWorkflowDecline =
        !isOwnRequest &&
        currentRequest.status === 'recommended_for_approval' &&
        hasWorkflowPermission('loan.decline');
    const canConvertToLoan =
        !isOwnRequest &&
        currentRequest.status === 'approved' &&
        hasWorkflowPermission('loan.convert_to_loan');
    const isWorkflowProcessing =
        workflowProcessingIds[currentRequest.id] ?? false;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Loan request" />
            <LoanRequestDetailPage
                loanRequest={currentRequest}
                applicant={currentApplicant}
                coMakerOne={currentCoMakerOne}
                coMakerTwo={currentCoMakerTwo}
                backHref={requestsIndex().url}
                backLabel="Back to workflow queue"
                pdfHref={pdfHref}
                printHref={printHref}
                approvedDocumentHrefs={approvedDocumentHrefs}
                workflow={{
                    startReview: canStartReview
                        ? {
                              show: true,
                              isProcessing: isWorkflowProcessing,
                              onSubmit: (payload) =>
                                  startReview(currentRequest.id, payload),
                          }
                        : undefined,
                    requestRevision: canRequestRevision
                        ? {
                              show: true,
                              isProcessing: isWorkflowProcessing,
                              onSubmit: (payload) =>
                                  requestRevision(currentRequest.id, payload),
                          }
                        : undefined,
                    reject: canReject
                        ? {
                              show: true,
                              isProcessing: isWorkflowProcessing,
                              onSubmit: (payload) =>
                                  rejectLoanRequest(
                                      currentRequest.id,
                                      payload,
                                  ),
                          }
                        : undefined,
                    recommendApproval: canRecommendApproval
                        ? {
                              show: true,
                              isProcessing: isWorkflowProcessing,
                              onSubmit: (payload) =>
                                  recommendApproval(
                                      currentRequest.id,
                                      payload,
                                  ),
                          }
                        : undefined,
                    approve: canWorkflowApprove
                        ? {
                              show: true,
                              isProcessing: isWorkflowProcessing,
                              onSubmit: (payload) =>
                                  approveLoanRequest(
                                      currentRequest.id,
                                      payload,
                                  ),
                          }
                        : undefined,
                    decline: canWorkflowDecline
                        ? {
                              show: true,
                              isProcessing: isWorkflowProcessing,
                              onSubmit: (payload) =>
                                  declineLoanRequest(
                                      currentRequest.id,
                                      payload,
                                  ),
                          }
                        : undefined,
                    convertToLoan: canConvertToLoan
                        ? {
                              show: true,
                              isProcessing: isWorkflowProcessing,
                              onSubmit: (payload) =>
                                  convertToLoan(currentRequest.id, payload),
                          }
                        : undefined,
                }}
            />
        </AppLayout>
    );
}

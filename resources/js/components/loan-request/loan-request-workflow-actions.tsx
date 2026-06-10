import { useEffect, useState, type FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
import { Separator } from '@/components/ui/separator';
import type { LoanRequestDetail } from '@/types/loan-requests';

type AsyncResult = Promise<unknown> | unknown;

export type LoanRequestWorkflowStartReviewPayload = {
    remarks?: string | null;
};

export type LoanRequestWorkflowRequestRevisionPayload = {
    remarks: string;
};

export type LoanRequestWorkflowRejectPayload = {
    rejection_reason: string;
};

export type LoanRequestWorkflowRecommendApprovalPayload = {
    review_remarks?: string | null;
};

export type LoanRequestWorkflowApprovePayload = {
    approved_amount: string;
    approved_term: string;
    approved_interest_rate?: string | null;
    approval_remarks?: string | null;
};

export type LoanRequestWorkflowDeclinePayload = {
    decline_reason: string;
};

export type LoanRequestWorkflowConvertToLoanPayload = {
    remarks?: string | null;
};

type LoanRequestWorkflowActionConfig<TPayload> = {
    show?: boolean;
    isProcessing?: boolean;
    onSubmit?: (payload: TPayload) => AsyncResult;
};

export type LoanRequestWorkflowProps = {
    startReview?: LoanRequestWorkflowActionConfig<LoanRequestWorkflowStartReviewPayload>;
    requestRevision?: LoanRequestWorkflowActionConfig<LoanRequestWorkflowRequestRevisionPayload>;
    reject?: LoanRequestWorkflowActionConfig<LoanRequestWorkflowRejectPayload>;
    recommendApproval?: LoanRequestWorkflowActionConfig<LoanRequestWorkflowRecommendApprovalPayload>;
    approve?: LoanRequestWorkflowActionConfig<LoanRequestWorkflowApprovePayload>;
    decline?: LoanRequestWorkflowActionConfig<LoanRequestWorkflowDeclinePayload>;
    convertToLoan?: LoanRequestWorkflowActionConfig<LoanRequestWorkflowConvertToLoanPayload>;
};

type Props = {
    loanRequest: LoanRequestDetail;
    workflow?: LoanRequestWorkflowProps;
};

const textareaClassName =
    'flex min-h-[112px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50';

const inputClassName =
    'border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50';

export function LoanRequestWorkflowActions({
    loanRequest,
    workflow,
}: Props) {
    const [isStartReviewOpen, setIsStartReviewOpen] = useState(false);
    const [isRequestRevisionOpen, setIsRequestRevisionOpen] = useState(false);
    const [isRejectOpen, setIsRejectOpen] = useState(false);
    const [isRecommendOpen, setIsRecommendOpen] = useState(false);
    const [isApproveOpen, setIsApproveOpen] = useState(false);
    const [isDeclineOpen, setIsDeclineOpen] = useState(false);
    const [isConvertOpen, setIsConvertOpen] = useState(false);

    const [startReviewRemarks, setStartReviewRemarks] = useState('');
    const [revisionRemarks, setRevisionRemarks] = useState('');
    const [revisionRemarksError, setRevisionRemarksError] = useState<
        string | null
    >(null);
    const [rejectionReason, setRejectionReason] = useState('');
    const [rejectionReasonError, setRejectionReasonError] = useState<
        string | null
    >(null);
    const [recommendRemarks, setRecommendRemarks] = useState('');
    const [approvedAmount, setApprovedAmount] = useState('');
    const [approvedTerm, setApprovedTerm] = useState('');
    const [approvedInterestRate, setApprovedInterestRate] = useState('');
    const [approvalRemarks, setApprovalRemarks] = useState('');
    const [declineReason, setDeclineReason] = useState('');
    const [declineReasonError, setDeclineReasonError] = useState<
        string | null
    >(null);
    const [convertRemarks, setConvertRemarks] = useState('');

    const hasOfficerActions =
        Boolean(workflow?.startReview?.show) ||
        Boolean(workflow?.requestRevision?.show) ||
        Boolean(workflow?.reject?.show) ||
        Boolean(workflow?.recommendApproval?.show);
    const hasManagerActions =
        Boolean(workflow?.approve?.show) || Boolean(workflow?.decline?.show);
    const hasConvertAction = Boolean(workflow?.convertToLoan?.show);

    useEffect(() => {
        const nextAmount =
            loanRequest.approved_amount ?? loanRequest.requested_amount ?? '';
        const nextTerm =
            loanRequest.approved_term ?? loanRequest.requested_term ?? '';

        setApprovedAmount(
            `${nextAmount}`.trim() !== '' ? `${nextAmount}` : '',
        );
        setApprovedTerm(`${nextTerm}`.trim() !== '' ? `${nextTerm}` : '');
        setApprovedInterestRate(
            loanRequest.approved_interest_rate !== null &&
                loanRequest.approved_interest_rate !== undefined &&
                `${loanRequest.approved_interest_rate}`.trim() !== ''
                ? `${loanRequest.approved_interest_rate}`
                : '',
        );
        setApprovalRemarks(loanRequest.approval_remarks ?? '');
    }, [
        loanRequest.approved_amount,
        loanRequest.approved_interest_rate,
        loanRequest.approved_term,
        loanRequest.approval_remarks,
        loanRequest.requested_amount,
        loanRequest.requested_term,
    ]);

    if (!hasOfficerActions && !hasManagerActions && !hasConvertAction) {
        return null;
    }

    const submitStartReview = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const result = await workflow?.startReview?.onSubmit?.({
            remarks: startReviewRemarks.trim() || null,
        });

        if (result) {
            setStartReviewRemarks('');
            setIsStartReviewOpen(false);
        }
    };

    const submitRequestRevision = async (
        event: FormEvent<HTMLFormElement>,
    ) => {
        event.preventDefault();

        const remarks = revisionRemarks.trim();

        if (remarks === '') {
            setRevisionRemarksError('Revision remarks are required.');
            return;
        }

        setRevisionRemarksError(null);

        const result = await workflow?.requestRevision?.onSubmit?.({
            remarks,
        });

        if (result) {
            setRevisionRemarks('');
            setIsRequestRevisionOpen(false);
        }
    };

    const submitReject = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const reason = rejectionReason.trim();

        if (reason === '') {
            setRejectionReasonError('Rejection reason is required.');
            return;
        }

        setRejectionReasonError(null);

        const result = await workflow?.reject?.onSubmit?.({
            rejection_reason: reason,
        });

        if (result) {
            setRejectionReason('');
            setIsRejectOpen(false);
        }
    };

    const submitRecommendApproval = async (
        event: FormEvent<HTMLFormElement>,
    ) => {
        event.preventDefault();

        const result = await workflow?.recommendApproval?.onSubmit?.({
            review_remarks: recommendRemarks.trim() || null,
        });

        if (result) {
            setRecommendRemarks('');
            setIsRecommendOpen(false);
        }
    };

    const submitApprove = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const result = await workflow?.approve?.onSubmit?.({
            approved_amount: approvedAmount,
            approved_term: approvedTerm,
            approved_interest_rate: approvedInterestRate.trim() || null,
            approval_remarks: approvalRemarks.trim() || null,
        });

        if (result) {
            setIsApproveOpen(false);
        }
    };

    const submitDecline = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const reason = declineReason.trim();

        if (reason === '') {
            setDeclineReasonError('Decline reason is required.');
            return;
        }

        setDeclineReasonError(null);

        const result = await workflow?.decline?.onSubmit?.({
            decline_reason: reason,
        });

        if (result) {
            setDeclineReason('');
            setIsDeclineOpen(false);
        }
    };

    const submitConvertToLoan = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const result = await workflow?.convertToLoan?.onSubmit?.({
            remarks: convertRemarks.trim() || null,
        });

        if (result) {
            setConvertRemarks('');
            setIsConvertOpen(false);
        }
    };

    return (
        <>
            {hasOfficerActions ? (
                <div className="space-y-3">
                    <div className="space-y-1">
                        <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            Review actions
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Officer actions are limited by the current workflow
                            status and server-side permissions.
                        </p>
                    </div>
                    <div className="grid gap-2">
                        {workflow?.startReview?.show ? (
                            <Button
                                type="button"
                                className="w-full justify-start"
                                disabled={workflow.startReview.isProcessing}
                                onClick={() => setIsStartReviewOpen(true)}
                            >
                                Start Review
                            </Button>
                        ) : null}
                        {workflow?.requestRevision?.show ? (
                            <Button
                                type="button"
                                variant="outline"
                                className="w-full justify-start"
                                disabled={workflow.requestRevision.isProcessing}
                                onClick={() => setIsRequestRevisionOpen(true)}
                            >
                                Request Revision
                            </Button>
                        ) : null}
                        {workflow?.reject?.show ? (
                            <Button
                                type="button"
                                variant="destructive"
                                className="w-full justify-start"
                                disabled={workflow.reject.isProcessing}
                                onClick={() => setIsRejectOpen(true)}
                            >
                                Reject
                            </Button>
                        ) : null}
                        {workflow?.recommendApproval?.show ? (
                            <Button
                                type="button"
                                className="w-full justify-start"
                                disabled={
                                    workflow.recommendApproval.isProcessing
                                }
                                onClick={() => setIsRecommendOpen(true)}
                            >
                                Recommend Approval
                            </Button>
                        ) : null}
                    </div>
                    <Separator className="bg-border/40" />
                </div>
            ) : null}

            {hasManagerActions ? (
                <div className="space-y-3">
                    <div className="space-y-1">
                        <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            Decision actions
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Manager decisions apply only to recommended
                            requests.
                        </p>
                    </div>
                    <div className="grid gap-2">
                        {workflow?.approve?.show ? (
                            <Button
                                type="button"
                                className="w-full justify-start"
                                disabled={workflow.approve.isProcessing}
                                onClick={() => setIsApproveOpen(true)}
                            >
                                Approve
                            </Button>
                        ) : null}
                        {workflow?.decline?.show ? (
                            <Button
                                type="button"
                                variant="destructive"
                                className="w-full justify-start"
                                disabled={workflow.decline.isProcessing}
                                onClick={() => setIsDeclineOpen(true)}
                            >
                                Decline
                            </Button>
                        ) : null}
                    </div>
                    <Separator className="bg-border/40" />
                </div>
            ) : null}

            {hasConvertAction ? (
                <div className="space-y-3">
                    <div className="space-y-1">
                        <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            Conversion
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Create the actual loan record once the request is
                            approved.
                        </p>
                    </div>
                    <Button
                        type="button"
                        className="w-full justify-start"
                        disabled={workflow?.convertToLoan?.isProcessing}
                        onClick={() => setIsConvertOpen(true)}
                    >
                        Convert to Loan
                    </Button>
                    <Separator className="bg-border/40" />
                </div>
            ) : null}

            <Dialog open={isStartReviewOpen} onOpenChange={setIsStartReviewOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Start Review</DialogTitle>
                        <DialogDescription>
                            Move this request from Pending Review to Under
                            Review. Add optional review remarks if needed.
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submitStartReview}>
                        <div className="space-y-2">
                            <Label htmlFor="start_review_remarks">
                                Review remarks
                            </Label>
                            <textarea
                                id="start_review_remarks"
                                className={textareaClassName}
                                maxLength={1000}
                                value={startReviewRemarks}
                                disabled={workflow?.startReview?.isProcessing}
                                onChange={(event) =>
                                    setStartReviewRemarks(event.target.value)
                                }
                            />
                            <div className="text-right text-xs text-muted-foreground">
                                {startReviewRemarks.length}/1000
                            </div>
                        </div>
                        <DialogFooter className="gap-2 sm:gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={workflow?.startReview?.isProcessing}
                                onClick={() => setIsStartReviewOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={workflow?.startReview?.isProcessing}
                            >
                                Start Review
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={isRequestRevisionOpen}
                onOpenChange={setIsRequestRevisionOpen}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Request Revision</DialogTitle>
                        <DialogDescription>
                            Explain what the member needs to revise before this
                            request can continue.
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submitRequestRevision}>
                        <div className="space-y-2">
                            <Label htmlFor="request_revision_remarks">
                                Revision remarks
                            </Label>
                            <textarea
                                id="request_revision_remarks"
                                className={textareaClassName}
                                maxLength={1000}
                                required
                                value={revisionRemarks}
                                disabled={
                                    workflow?.requestRevision?.isProcessing
                                }
                                onChange={(event) => {
                                    setRevisionRemarks(event.target.value);
                                    setRevisionRemarksError(null);
                                }}
                            />
                            <div className="flex items-start justify-between gap-3">
                                <InputError
                                    message={revisionRemarksError ?? ''}
                                />
                                <span className="ml-auto text-xs text-muted-foreground">
                                    {revisionRemarks.length}/1000
                                </span>
                            </div>
                        </div>
                        <DialogFooter className="gap-2 sm:gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={
                                    workflow?.requestRevision?.isProcessing
                                }
                                onClick={() => setIsRequestRevisionOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={
                                    workflow?.requestRevision?.isProcessing
                                }
                            >
                                Request Revision
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={isRejectOpen} onOpenChange={setIsRejectOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Reject Request</DialogTitle>
                        <DialogDescription>
                            Record the rejection reason for this request.
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submitReject}>
                        <div className="space-y-2">
                            <Label htmlFor="rejection_reason">
                                Rejection reason
                            </Label>
                            <textarea
                                id="rejection_reason"
                                className={textareaClassName}
                                maxLength={1000}
                                required
                                value={rejectionReason}
                                disabled={workflow?.reject?.isProcessing}
                                onChange={(event) => {
                                    setRejectionReason(event.target.value);
                                    setRejectionReasonError(null);
                                }}
                            />
                            <div className="flex items-start justify-between gap-3">
                                <InputError
                                    message={rejectionReasonError ?? ''}
                                />
                                <span className="ml-auto text-xs text-muted-foreground">
                                    {rejectionReason.length}/1000
                                </span>
                            </div>
                        </div>
                        <DialogFooter className="gap-2 sm:gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={workflow?.reject?.isProcessing}
                                onClick={() => setIsRejectOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={workflow?.reject?.isProcessing}
                            >
                                Reject
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={isRecommendOpen} onOpenChange={setIsRecommendOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Recommend Approval</DialogTitle>
                        <DialogDescription>
                            Add optional remarks before forwarding this request
                            to a loan manager.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        className="space-y-4"
                        onSubmit={submitRecommendApproval}
                    >
                        <div className="space-y-2">
                            <Label htmlFor="recommend_approval_remarks">
                                Review remarks
                            </Label>
                            <textarea
                                id="recommend_approval_remarks"
                                className={textareaClassName}
                                maxLength={1000}
                                value={recommendRemarks}
                                disabled={
                                    workflow?.recommendApproval?.isProcessing
                                }
                                onChange={(event) =>
                                    setRecommendRemarks(event.target.value)
                                }
                            />
                            <div className="text-right text-xs text-muted-foreground">
                                {recommendRemarks.length}/1000
                            </div>
                        </div>
                        <DialogFooter className="gap-2 sm:gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={
                                    workflow?.recommendApproval?.isProcessing
                                }
                                onClick={() => setIsRecommendOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={
                                    workflow?.recommendApproval?.isProcessing
                                }
                            >
                                Recommend Approval
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={isApproveOpen} onOpenChange={setIsApproveOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Approve Request</DialogTitle>
                        <DialogDescription>
                            Confirm the approved amount and terms before moving
                            this request to Approved.
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submitApprove}>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="workflow_approved_amount">
                                    Approved amount
                                </Label>
                                <Input
                                    id="workflow_approved_amount"
                                    type="number"
                                    min="1"
                                    step="0.01"
                                    required
                                    value={approvedAmount}
                                    className={inputClassName}
                                    disabled={workflow?.approve?.isProcessing}
                                    onChange={(event) =>
                                        setApprovedAmount(event.target.value)
                                    }
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="workflow_approved_term">
                                    Approved term
                                </Label>
                                <Input
                                    id="workflow_approved_term"
                                    type="number"
                                    min="1"
                                    step="1"
                                    required
                                    value={approvedTerm}
                                    className={inputClassName}
                                    disabled={workflow?.approve?.isProcessing}
                                    onChange={(event) =>
                                        setApprovedTerm(event.target.value)
                                    }
                                />
                            </div>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="workflow_approved_interest_rate">
                                Approved interest rate
                            </Label>
                            <Input
                                id="workflow_approved_interest_rate"
                                type="number"
                                min="0"
                                step="0.01"
                                value={approvedInterestRate}
                                className={inputClassName}
                                disabled={workflow?.approve?.isProcessing}
                                onChange={(event) =>
                                    setApprovedInterestRate(event.target.value)
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="workflow_approval_remarks">
                                Approval remarks
                            </Label>
                            <textarea
                                id="workflow_approval_remarks"
                                className={textareaClassName}
                                maxLength={1000}
                                value={approvalRemarks}
                                disabled={workflow?.approve?.isProcessing}
                                onChange={(event) =>
                                    setApprovalRemarks(event.target.value)
                                }
                            />
                            <div className="text-right text-xs text-muted-foreground">
                                {approvalRemarks.length}/1000
                            </div>
                        </div>
                        <DialogFooter className="gap-2 sm:gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={workflow?.approve?.isProcessing}
                                onClick={() => setIsApproveOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={workflow?.approve?.isProcessing}
                            >
                                Approve Request
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={isDeclineOpen} onOpenChange={setIsDeclineOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Decline Request</DialogTitle>
                        <DialogDescription>
                            Record the reason for declining this recommended
                            request.
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submitDecline}>
                        <div className="space-y-2">
                            <Label htmlFor="workflow_decline_reason">
                                Decline reason
                            </Label>
                            <textarea
                                id="workflow_decline_reason"
                                className={textareaClassName}
                                maxLength={1000}
                                required
                                value={declineReason}
                                disabled={workflow?.decline?.isProcessing}
                                onChange={(event) => {
                                    setDeclineReason(event.target.value);
                                    setDeclineReasonError(null);
                                }}
                            />
                            <div className="flex items-start justify-between gap-3">
                                <InputError
                                    message={declineReasonError ?? ''}
                                />
                                <span className="ml-auto text-xs text-muted-foreground">
                                    {declineReason.length}/1000
                                </span>
                            </div>
                        </div>
                        <DialogFooter className="gap-2 sm:gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={workflow?.decline?.isProcessing}
                                onClick={() => setIsDeclineOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={workflow?.decline?.isProcessing}
                            >
                                Decline
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={isConvertOpen} onOpenChange={setIsConvertOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Convert to Loan</DialogTitle>
                        <DialogDescription>
                            Create the actual loan record for this approved
                            request. This action cannot be repeated.
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submitConvertToLoan}>
                        <div className="space-y-2">
                            <Label htmlFor="workflow_convert_remarks">
                                Remarks
                            </Label>
                            <textarea
                                id="workflow_convert_remarks"
                                className={textareaClassName}
                                maxLength={1000}
                                value={convertRemarks}
                                disabled={workflow?.convertToLoan?.isProcessing}
                                onChange={(event) =>
                                    setConvertRemarks(event.target.value)
                                }
                            />
                            <div className="text-right text-xs text-muted-foreground">
                                {convertRemarks.length}/1000
                            </div>
                        </div>
                        <DialogFooter className="gap-2 sm:gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={workflow?.convertToLoan?.isProcessing}
                                onClick={() => setIsConvertOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={workflow?.convertToLoan?.isProcessing}
                            >
                                Convert to Loan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

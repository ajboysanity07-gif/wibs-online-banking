import { useEffect, useMemo, useState, type FormEvent } from 'react';
import InputError from '@/components/input-error';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import type {
    LoanRequestAssignmentOfficerOption,
    LoanRequestDetail,
} from '@/types/loan-requests';

type AsyncResult = Promise<unknown> | unknown;

export type LoanRequestWorkflowClaimPayload = Record<string, never>;

export type LoanRequestWorkflowAssignmentPayload = {
    officer_user_id: number;
    reason: string;
};

export type LoanRequestWorkflowReturnToQueuePayload = {
    reason: string;
};

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

type LoanRequestWorkflowActionConfig<TPayload> = {
    show?: boolean;
    isProcessing?: boolean;
    onSubmit?: (payload: TPayload) => AsyncResult;
};

type LoanRequestWorkflowClaimActionConfig = {
    show?: boolean;
    isProcessing?: boolean;
    onSubmit?: () => AsyncResult;
};

type LoanRequestWorkflowAssignmentActionConfig = {
    show?: boolean;
    isProcessing?: boolean;
    officerOptions: LoanRequestAssignmentOfficerOption[];
    onSubmit?: (payload: LoanRequestWorkflowAssignmentPayload) => AsyncResult;
};

export type LoanRequestWorkflowProps = {
    claim?: LoanRequestWorkflowClaimActionConfig;
    assign?: LoanRequestWorkflowAssignmentActionConfig;
    reassign?: LoanRequestWorkflowAssignmentActionConfig;
    returnToQueue?: LoanRequestWorkflowActionConfig<LoanRequestWorkflowReturnToQueuePayload>;
    startReview?: LoanRequestWorkflowActionConfig<LoanRequestWorkflowStartReviewPayload>;
    requestRevision?: LoanRequestWorkflowActionConfig<LoanRequestWorkflowRequestRevisionPayload>;
    reject?: LoanRequestWorkflowActionConfig<LoanRequestWorkflowRejectPayload>;
    recommendApproval?: LoanRequestWorkflowActionConfig<LoanRequestWorkflowRecommendApprovalPayload>;
    approve?: LoanRequestWorkflowActionConfig<LoanRequestWorkflowApprovePayload>;
    decline?: LoanRequestWorkflowActionConfig<LoanRequestWorkflowDeclinePayload>;
};

type Props = {
    loanRequest: LoanRequestDetail;
    workflow?: LoanRequestWorkflowProps;
};

const textareaClassName =
    'flex min-h-[112px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50';

const inputClassName =
    'border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50';

const buildOfficerLabel = (
    officer: LoanRequestAssignmentOfficerOption,
): string => {
    const activeCountLabel = `${officer.active_assignment_count} active application${officer.active_assignment_count === 1 ? '' : 's'}`;

    return officer.has_workload_warning
        ? `${officer.name} - ${activeCountLabel} - ${officer.workload_warning_label ?? 'High workload'}`
        : `${officer.name} - ${activeCountLabel}`;
};

const OfficerSummary = ({
    officer,
}: {
    officer: LoanRequestAssignmentOfficerOption;
}) => (
    <div className="space-y-3 rounded-lg border border-border/50 bg-muted/20 p-3">
        <div className="space-y-1">
            <p className="text-sm font-semibold text-foreground">
                {officer.name}
            </p>
            <p className="text-xs text-muted-foreground">
                {buildOfficerLabel(officer)}
            </p>
        </div>
        {officer.has_workload_warning ? (
            <Alert className="border-amber-500/35 bg-amber-500/10">
                <AlertTitle>High workload</AlertTitle>
                <AlertDescription>
                    This officer is already handling{' '}
                    {officer.active_assignment_count} active applications.
                    Assignment is still allowed.
                </AlertDescription>
            </Alert>
        ) : null}
    </div>
);

export function LoanRequestWorkflowActions({
    loanRequest,
    workflow,
}: Props) {
    const [isAssignOpen, setIsAssignOpen] = useState(false);
    const [isReassignOpen, setIsReassignOpen] = useState(false);
    const [isReturnToQueueOpen, setIsReturnToQueueOpen] = useState(false);
    const [isStartReviewOpen, setIsStartReviewOpen] = useState(false);
    const [isRequestRevisionOpen, setIsRequestRevisionOpen] = useState(false);
    const [isRejectOpen, setIsRejectOpen] = useState(false);
    const [isRecommendOpen, setIsRecommendOpen] = useState(false);
    const [isApproveOpen, setIsApproveOpen] = useState(false);
    const [isDeclineOpen, setIsDeclineOpen] = useState(false);

    const [assignOfficerUserId, setAssignOfficerUserId] = useState('');
    const [assignReason, setAssignReason] = useState('');
    const [assignReasonError, setAssignReasonError] = useState<string | null>(
        null,
    );
    const [reassignOfficerUserId, setReassignOfficerUserId] = useState('');
    const [reassignReason, setReassignReason] = useState('');
    const [reassignReasonError, setReassignReasonError] = useState<
        string | null
    >(null);
    const [returnToQueueReason, setReturnToQueueReason] = useState('');
    const [returnToQueueReasonError, setReturnToQueueReasonError] = useState<
        string | null
    >(null);
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

    const assignOfficerOptions = workflow?.assign?.officerOptions ?? [];
    const reassignOfficerOptions = useMemo(
        () =>
            (workflow?.reassign?.officerOptions ?? []).filter(
                (officer) => officer.user_id !== loanRequest.assigned_officer_id,
            ),
        [loanRequest.assigned_officer_id, workflow?.reassign?.officerOptions],
    );

    const selectedAssignOfficer =
        assignOfficerOptions.find(
            (officer) => `${officer.user_id}` === assignOfficerUserId,
        ) ?? null;
    const selectedReassignOfficer =
        reassignOfficerOptions.find(
            (officer) => `${officer.user_id}` === reassignOfficerUserId,
        ) ?? null;

    const hasAssignmentActions =
        Boolean(workflow?.claim?.show) ||
        Boolean(workflow?.assign?.show) ||
        Boolean(workflow?.reassign?.show) ||
        Boolean(workflow?.returnToQueue?.show);
    const hasOfficerActions =
        Boolean(workflow?.startReview?.show) ||
        Boolean(workflow?.requestRevision?.show) ||
        Boolean(workflow?.reject?.show) ||
        Boolean(workflow?.recommendApproval?.show);
    const hasManagerActions =
        Boolean(workflow?.approve?.show) || Boolean(workflow?.decline?.show);

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

    useEffect(() => {
        if (
            assignOfficerOptions.length > 0 &&
            ! assignOfficerOptions.some(
                (officer) => `${officer.user_id}` === assignOfficerUserId,
            )
        ) {
            setAssignOfficerUserId(`${assignOfficerOptions[0].user_id}`);
        }
    }, [assignOfficerOptions, assignOfficerUserId]);

    useEffect(() => {
        if (
            reassignOfficerOptions.length > 0 &&
            ! reassignOfficerOptions.some(
                (officer) => `${officer.user_id}` === reassignOfficerUserId,
            )
        ) {
            setReassignOfficerUserId(`${reassignOfficerOptions[0].user_id}`);
        }
    }, [reassignOfficerOptions, reassignOfficerUserId]);

    if (!hasAssignmentActions && !hasOfficerActions && !hasManagerActions) {
        return null;
    }

    const submitAssign = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const officerUserId = Number(assignOfficerUserId);
        const reason = assignReason.trim();

        if (!Number.isInteger(officerUserId) || officerUserId <= 0) {
            setAssignReasonError('Select a Loan Officer before continuing.');
            return;
        }

        if (reason === '') {
            setAssignReasonError('Assignment reason is required.');
            return;
        }

        setAssignReasonError(null);

        const result = await workflow?.assign?.onSubmit?.({
            officer_user_id: officerUserId,
            reason,
        });

        if (result) {
            setAssignReason('');
            setIsAssignOpen(false);
        }
    };

    const submitReassign = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const officerUserId = Number(reassignOfficerUserId);
        const reason = reassignReason.trim();

        if (!Number.isInteger(officerUserId) || officerUserId <= 0) {
            setReassignReasonError('Select a Loan Officer before continuing.');
            return;
        }

        if (reason === '') {
            setReassignReasonError('Reassignment reason is required.');
            return;
        }

        setReassignReasonError(null);

        const result = await workflow?.reassign?.onSubmit?.({
            officer_user_id: officerUserId,
            reason,
        });

        if (result) {
            setReassignReason('');
            setIsReassignOpen(false);
        }
    };

    const submitReturnToQueue = async (
        event: FormEvent<HTMLFormElement>,
    ) => {
        event.preventDefault();

        const reason = returnToQueueReason.trim();

        if (reason === '') {
            setReturnToQueueReasonError('Reason is required.');
            return;
        }

        setReturnToQueueReasonError(null);

        const result = await workflow?.returnToQueue?.onSubmit?.({
            reason,
        });

        if (result) {
            setReturnToQueueReason('');
            setIsReturnToQueueOpen(false);
        }
    };

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

    return (
        <>
            {hasAssignmentActions ? (
                <div className="space-y-3">
                    <div className="space-y-1">
                        <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            Assignment actions
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Assignment changes follow the current request status
                            and your server-side permissions.
                        </p>
                    </div>
                    <div className="grid gap-2">
                        {workflow?.claim?.show ? (
                            <Button
                                type="button"
                                variant="outline"
                                className="w-full justify-start"
                                disabled={workflow.claim.isProcessing}
                                onClick={() => void workflow.claim?.onSubmit?.()}
                            >
                                Claim
                            </Button>
                        ) : null}
                        {workflow?.assign?.show ? (
                            <Button
                                type="button"
                                variant="outline"
                                className="w-full justify-start"
                                disabled={workflow.assign.isProcessing}
                                onClick={() => setIsAssignOpen(true)}
                            >
                                Assign Officer
                            </Button>
                        ) : null}
                        {workflow?.reassign?.show ? (
                            <Button
                                type="button"
                                variant="outline"
                                className="w-full justify-start"
                                disabled={workflow.reassign.isProcessing}
                                onClick={() => setIsReassignOpen(true)}
                            >
                                Reassign Officer
                            </Button>
                        ) : null}
                        {workflow?.returnToQueue?.show ? (
                            <Button
                                type="button"
                                variant="outline"
                                className="w-full justify-start"
                                disabled={workflow.returnToQueue.isProcessing}
                                onClick={() => setIsReturnToQueueOpen(true)}
                            >
                                Return to Queue
                            </Button>
                        ) : null}
                    </div>
                    <Separator className="bg-border/40" />
                </div>
            ) : null}

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

            <Dialog open={isAssignOpen} onOpenChange={setIsAssignOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Assign Loan Officer</DialogTitle>
                        <DialogDescription>
                            Select an active Loan Officer and record the reason
                            for the assignment.
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submitAssign}>
                        <div className="space-y-2">
                            <Label htmlFor="assign_officer_user_id">
                                Loan Officer
                            </Label>
                            <Select
                                value={assignOfficerUserId}
                                onValueChange={(value) => {
                                    setAssignOfficerUserId(value);
                                    setAssignReasonError(null);
                                }}
                            >
                                <SelectTrigger
                                    id="assign_officer_user_id"
                                    aria-label="Loan Officer"
                                >
                                    <SelectValue placeholder="Select a Loan Officer" />
                                </SelectTrigger>
                                <SelectContent>
                                    {assignOfficerOptions.map((officer) => (
                                        <SelectItem
                                            key={officer.user_id}
                                            value={`${officer.user_id}`}
                                        >
                                            {buildOfficerLabel(officer)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        {selectedAssignOfficer ? (
                            <OfficerSummary officer={selectedAssignOfficer} />
                        ) : null}
                        <div className="space-y-2">
                            <Label htmlFor="assign_reason">Reason</Label>
                            <textarea
                                id="assign_reason"
                                className={textareaClassName}
                                maxLength={1000}
                                required
                                value={assignReason}
                                disabled={workflow?.assign?.isProcessing}
                                onChange={(event) => {
                                    setAssignReason(event.target.value);
                                    setAssignReasonError(null);
                                }}
                            />
                            <div className="flex items-start justify-between gap-3">
                                <InputError message={assignReasonError ?? ''} />
                                <span className="ml-auto text-xs text-muted-foreground">
                                    {assignReason.length}/1000
                                </span>
                            </div>
                        </div>
                        <DialogFooter className="gap-2 sm:gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={workflow?.assign?.isProcessing}
                                onClick={() => setIsAssignOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={
                                    workflow?.assign?.isProcessing ||
                                    assignOfficerOptions.length === 0
                                }
                            >
                                Assign Officer
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={isReassignOpen} onOpenChange={setIsReassignOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Reassign Loan Officer</DialogTitle>
                        <DialogDescription>
                            Move this application to another active Loan
                            Officer and record the reason.
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submitReassign}>
                        <div className="space-y-2">
                            <Label htmlFor="reassign_officer_user_id">
                                Loan Officer
                            </Label>
                            <Select
                                value={reassignOfficerUserId}
                                onValueChange={(value) => {
                                    setReassignOfficerUserId(value);
                                    setReassignReasonError(null);
                                }}
                            >
                                <SelectTrigger
                                    id="reassign_officer_user_id"
                                    aria-label="Loan Officer"
                                >
                                    <SelectValue placeholder="Select a Loan Officer" />
                                </SelectTrigger>
                                <SelectContent>
                                    {reassignOfficerOptions.map((officer) => (
                                        <SelectItem
                                            key={officer.user_id}
                                            value={`${officer.user_id}`}
                                        >
                                            {buildOfficerLabel(officer)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        {selectedReassignOfficer ? (
                            <OfficerSummary officer={selectedReassignOfficer} />
                        ) : null}
                        <div className="space-y-2">
                            <Label htmlFor="reassign_reason">Reason</Label>
                            <textarea
                                id="reassign_reason"
                                className={textareaClassName}
                                maxLength={1000}
                                required
                                value={reassignReason}
                                disabled={workflow?.reassign?.isProcessing}
                                onChange={(event) => {
                                    setReassignReason(event.target.value);
                                    setReassignReasonError(null);
                                }}
                            />
                            <div className="flex items-start justify-between gap-3">
                                <InputError
                                    message={reassignReasonError ?? ''}
                                />
                                <span className="ml-auto text-xs text-muted-foreground">
                                    {reassignReason.length}/1000
                                </span>
                            </div>
                        </div>
                        <DialogFooter className="gap-2 sm:gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={workflow?.reassign?.isProcessing}
                                onClick={() => setIsReassignOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={
                                    workflow?.reassign?.isProcessing ||
                                    reassignOfficerOptions.length === 0
                                }
                            >
                                Reassign Officer
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={isReturnToQueueOpen}
                onOpenChange={setIsReturnToQueueOpen}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Return to Queue</DialogTitle>
                        <DialogDescription>
                            Remove the current officer assignment without
                            changing the workflow status.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        className="space-y-4"
                        onSubmit={submitReturnToQueue}
                    >
                        <div className="rounded-lg border border-border/50 bg-muted/20 p-3 text-sm">
                            <p className="font-medium text-foreground">
                                Current assignee
                            </p>
                            <p className="text-muted-foreground">
                                {loanRequest.assigned_officer?.name ??
                                    'Unassigned'}
                            </p>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="return_to_queue_reason">
                                Reason
                            </Label>
                            <textarea
                                id="return_to_queue_reason"
                                className={textareaClassName}
                                maxLength={1000}
                                required
                                value={returnToQueueReason}
                                disabled={
                                    workflow?.returnToQueue?.isProcessing
                                }
                                onChange={(event) => {
                                    setReturnToQueueReason(event.target.value);
                                    setReturnToQueueReasonError(null);
                                }}
                            />
                            <div className="flex items-start justify-between gap-3">
                                <InputError
                                    message={returnToQueueReasonError ?? ''}
                                />
                                <span className="ml-auto text-xs text-muted-foreground">
                                    {returnToQueueReason.length}/1000
                                </span>
                            </div>
                        </div>
                        <DialogFooter className="gap-2 sm:gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={
                                    workflow?.returnToQueue?.isProcessing
                                }
                                onClick={() => setIsReturnToQueueOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={
                                    workflow?.returnToQueue?.isProcessing
                                }
                            >
                                Return to Queue
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={isStartReviewOpen} onOpenChange={setIsStartReviewOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Start Review</DialogTitle>
                        <DialogDescription>
                            Move this request from Pending Review to Under
                            Review. If the request is still unassigned, Start
                            Review will also claim it for you.
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
        </>
    );
}

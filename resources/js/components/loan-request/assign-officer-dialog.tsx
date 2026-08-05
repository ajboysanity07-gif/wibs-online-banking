import { useState } from 'react';
import { buildOfficerLabel } from '@/components/loan-request/loan-request-workflow-actions';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { LoanRequestAssignmentOfficerOption } from '@/types/loan-requests';

const textareaClassName =
    'flex min-h-[88px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    mode: 'assign' | 'reassign';
    officerOptions: LoanRequestAssignmentOfficerOption[];
    currentOfficerName?: string | null;
    isProcessing?: boolean;
    onSubmit: (officerUserId: number, reason: string) => Promise<unknown>;
};

export function AssignOfficerDialog({
    open,
    onOpenChange,
    mode,
    officerOptions,
    currentOfficerName,
    isProcessing = false,
    onSubmit,
}: Props) {
    const [officerUserId, setOfficerUserId] = useState('');
    const [reason, setReason] = useState('');
    const [reasonError, setReasonError] = useState<string | null>(null);

    const handleOpenChange = (nextOpen: boolean) => {
        if (!nextOpen) {
            setOfficerUserId('');
            setReason('');
            setReasonError(null);
        }
        onOpenChange(nextOpen);
    };

    const selectedOfficer =
        officerOptions.find(
            (option) => `${option.user_id}` === officerUserId,
        ) ?? null;

    const handleConfirm = async () => {
        if (!selectedOfficer) {
            setReasonError('Select a Loan Processor before continuing.');
            return;
        }

        if (reason.trim() === '') {
            setReasonError('Please provide a reason.');
            return;
        }

        await onSubmit(selectedOfficer.user_id, reason.trim());
        handleOpenChange(false);
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {mode === 'assign'
                            ? 'Assign loan processor'
                            : 'Reassign loan processor'}
                    </DialogTitle>
                    <DialogDescription>
                        {mode === 'reassign' && currentOfficerName
                            ? `Currently assigned to ${currentOfficerName}.`
                            : 'Select a loan processor and record the reason for the assignment.'}
                    </DialogDescription>
                </DialogHeader>
                <div className="space-y-4">
                    <div className="space-y-2">
                        <label
                            htmlFor="assign-officer-select"
                            className="text-xs font-medium text-muted-foreground"
                        >
                            Loan processor
                        </label>
                        <Select
                            value={officerUserId}
                            onValueChange={(value) => {
                                setOfficerUserId(value);
                                setReasonError(null);
                            }}
                            disabled={isProcessing}
                        >
                            <SelectTrigger
                                id="assign-officer-select"
                                aria-label="Loan processor"
                            >
                                <SelectValue placeholder="Select a loan processor" />
                            </SelectTrigger>
                            <SelectContent>
                                {officerOptions.map((officer) => (
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
                    <div className="space-y-2">
                        <label
                            htmlFor="assign-officer-reason"
                            className="text-xs font-medium text-muted-foreground"
                        >
                            Reason
                        </label>
                        <textarea
                            id="assign-officer-reason"
                            className={textareaClassName}
                            maxLength={1000}
                            value={reason}
                            disabled={isProcessing}
                            onChange={(event) => {
                                setReason(event.target.value);
                                setReasonError(null);
                            }}
                        />
                        {reasonError ? (
                            <p className="text-xs text-destructive">
                                {reasonError}
                            </p>
                        ) : null}
                    </div>
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => handleOpenChange(false)}
                        disabled={isProcessing}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        onClick={handleConfirm}
                        disabled={isProcessing}
                    >
                        Confirm
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

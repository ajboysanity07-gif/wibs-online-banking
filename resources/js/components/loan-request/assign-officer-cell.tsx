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
    mode: 'assign' | 'reassign';
    officerOptions: LoanRequestAssignmentOfficerOption[];
    currentOfficerName?: string | null;
    isProcessing?: boolean;
    onSubmit: (officerUserId: number, reason: string) => Promise<unknown>;
};

export function AssignOfficerCell({
    mode,
    officerOptions,
    currentOfficerName,
    isProcessing = false,
    onSubmit,
}: Props) {
    const [selectedOfficer, setSelectedOfficer] =
        useState<LoanRequestAssignmentOfficerOption | null>(null);
    const [reason, setReason] = useState('');
    const [reasonError, setReasonError] = useState<string | null>(null);

    const isOpen = selectedOfficer !== null;

    const closeDialog = () => {
        setSelectedOfficer(null);
        setReason('');
        setReasonError(null);
    };

    const handleOfficerPicked = (officerUserId: string) => {
        const officer = officerOptions.find(
            (option) => `${option.user_id}` === officerUserId,
        );

        if (!officer) {
            return;
        }

        setSelectedOfficer(officer);
        setReason(
            mode === 'assign'
                ? 'Assigned from requests list'
                : 'Reassigned from requests list',
        );
        setReasonError(null);
    };

    const handleConfirm = async () => {
        if (!selectedOfficer) {
            return;
        }

        if (reason.trim() === '') {
            setReasonError('Please provide a reason.');
            return;
        }

        await onSubmit(selectedOfficer.user_id, reason.trim());
        closeDialog();
    };

    return (
        <>
            {mode === 'assign' ? (
                <Select
                    value=""
                    onValueChange={handleOfficerPicked}
                    disabled={isProcessing}
                >
                    <SelectTrigger
                        aria-label="Assign loan processor"
                        className="h-8 w-[220px] text-xs"
                    >
                        <SelectValue placeholder="Assign loan processor" />
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
            ) : (
                <div className="flex items-center gap-2">
                    <span className="text-sm font-medium text-foreground">
                        {currentOfficerName}
                    </span>
                    <Select
                        value=""
                        onValueChange={handleOfficerPicked}
                        disabled={isProcessing}
                    >
                        <SelectTrigger
                            aria-label="Reassign loan processor"
                            className="h-7 w-[130px] text-xs"
                        >
                            <SelectValue placeholder="Reassign" />
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
            )}

            <Dialog
                open={isOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        closeDialog();
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {mode === 'assign'
                                ? 'Assign loan processor'
                                : 'Reassign loan processor'}
                        </DialogTitle>
                        <DialogDescription>
                            {selectedOfficer
                                ? `Confirm ${mode === 'assign' ? 'assigning' : 'reassigning'} this request to ${selectedOfficer.name}.`
                                : null}
                        </DialogDescription>
                    </DialogHeader>
                    {selectedOfficer ? (
                        <div className="space-y-3">
                            <p className="text-xs text-muted-foreground">
                                {buildOfficerLabel(selectedOfficer)}
                            </p>
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
                    ) : null}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={closeDialog}
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
        </>
    );
}

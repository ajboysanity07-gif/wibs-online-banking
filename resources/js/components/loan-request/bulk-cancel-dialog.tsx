import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const textareaClassName =
    'flex min-h-[88px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    requestCount: number;
    isProcessing?: boolean;
    onSubmit: (cancellationReason: string) => Promise<unknown>;
};

export function BulkCancelDialog({
    open,
    onOpenChange,
    requestCount,
    isProcessing = false,
    onSubmit,
}: Props) {
    const [reason, setReason] = useState('');
    const [reasonError, setReasonError] = useState<string | null>(null);

    const handleOpenChange = (nextOpen: boolean) => {
        if (!nextOpen) {
            setReason('');
            setReasonError(null);
        }
        onOpenChange(nextOpen);
    };

    const handleConfirm = async () => {
        if (reason.trim() === '') {
            setReasonError('Please provide a reason.');
            return;
        }

        await onSubmit(reason.trim());
        handleOpenChange(false);
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Cancel selected requests</DialogTitle>
                    <DialogDescription>
                        {`You're about to cancel ${requestCount} loan request${requestCount === 1 ? '' : 's'}. Record the reason for this cancellation.`}
                    </DialogDescription>
                </DialogHeader>
                <div className="space-y-2">
                    <label
                        htmlFor="bulk-cancel-reason"
                        className="text-xs font-medium text-muted-foreground"
                    >
                        Reason
                    </label>
                    <textarea
                        id="bulk-cancel-reason"
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
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => handleOpenChange(false)}
                        disabled={isProcessing}
                    >
                        Back
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={handleConfirm}
                        disabled={isProcessing}
                    >
                        Cancel requests
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

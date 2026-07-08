import {
    SnapshotRow,
    snapshotCurrency,
    snapshotDisplay,
    type InlineProcessingFormState,
} from '@/pages/staff/loan-request-show';
import type { LoanRequestDataFieldDefinition } from '@/types/loan-requests';

type Props = {
    processingForm: InlineProcessingFormState;
    processingFieldDefinitions: Record<string, LoanRequestDataFieldDefinition>;
    lastProcessingSavedAt: Date | null;
};

const requiredFieldDisplay = (value: string) => {
    const isFilled = value.trim() !== '';

    return (
        <span className="inline-flex items-center gap-1.5">
            <span
                className={
                    isFilled
                        ? 'inline-block size-1.5 rounded-full bg-emerald-500'
                        : 'inline-block size-1.5 rounded-full bg-amber-500'
                }
            />
            {isFilled ? snapshotDisplay(value) : 'Required — not yet filled'}
        </span>
    );
};

export function LoanRequestProcessingReviewStrip({
    processingForm,
    processingFieldDefinitions,
    lastProcessingSavedAt,
}: Props) {
    const recommendedTermDisplay =
        processingForm.recommended_term.trim() !== ''
            ? `${processingForm.recommended_term} months`
            : '—';

    return (
        <div className="rounded-lg border border-border/40 bg-muted/10 p-4">
            <div className="mb-3 flex items-center justify-between gap-3">
                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    Review
                </p>
                <p className="text-xs text-muted-foreground">
                    {lastProcessingSavedAt !== null
                        ? `Last saved ${lastProcessingSavedAt.toLocaleTimeString()}`
                        : 'Not saved yet'}
                </p>
            </div>
            <div className="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                <SnapshotRow
                    label="Recommended amount"
                    value={snapshotCurrency(processingForm.recommended_amount)}
                />
                <SnapshotRow
                    label="Recommended term"
                    value={recommendedTermDisplay}
                />
                <SnapshotRow
                    label="Recommended interest rate"
                    value={snapshotDisplay(
                        processingForm.recommended_interest_rate,
                    )}
                />
                <SnapshotRow
                    label="Payment frequency"
                    value={snapshotDisplay(
                        processingForm.recommended_payment_frequency,
                    )}
                />
                <SnapshotRow
                    label="Recommendation remarks"
                    value={snapshotDisplay(
                        processingForm.recommendation_remarks,
                    )}
                />
                {Object.entries(processingFieldDefinitions).map(
                    ([fieldKey, field]) => {
                        const value = processingForm.processing[fieldKey];
                        const display =
                            field.type === 'boolean'
                                ? value === true
                                    ? 'Yes'
                                    : value === false
                                      ? 'No'
                                      : '—'
                                : snapshotDisplay(
                                      value as string | number | null,
                                  );

                        return (
                            <SnapshotRow
                                key={fieldKey}
                                label={field.label}
                                value={display}
                            />
                        );
                    },
                )}
                <div className="space-y-1">
                    <p className="text-xs text-muted-foreground">Reason</p>
                    <p className="text-sm leading-relaxed font-medium">
                        {requiredFieldDisplay(processingForm.reason)}
                    </p>
                </div>
                <div className="space-y-1">
                    <p className="text-xs text-muted-foreground">
                        Information source
                    </p>
                    <p className="text-sm leading-relaxed font-medium">
                        {requiredFieldDisplay(
                            processingForm.information_source,
                        )}
                    </p>
                </div>
            </div>
        </div>
    );
}

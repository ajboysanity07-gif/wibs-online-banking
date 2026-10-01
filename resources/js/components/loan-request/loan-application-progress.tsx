import {
    guidedProgress,
    SECTION_LABELS,
    type ApplicationSection,
} from '@/lib/loan-application-flow';

/**
 * Guided first-pass indicator: five equal segments, "Step N of 5 · Section".
 * Mobile substeps (loan details 1/2, co-maker 1-3/3) partially fill the
 * current segment; the segment count never changes.
 */
export function LoanApplicationProgress({
    section,
    substep = 1,
    substeps = 1,
}: {
    section: ApplicationSection;
    substep?: number;
    substeps?: number;
}) {
    const { step, total, fills, value } = guidedProgress(
        section,
        substep,
        substeps,
    );
    const label = `Step ${step} of ${total} · ${SECTION_LABELS[section]}`;

    return (
        <div className="space-y-1.5">
            <div
                role="progressbar"
                aria-label="Application progress"
                aria-valuemin={0}
                aria-valuemax={total}
                aria-valuenow={value}
                aria-valuetext={label}
                className="flex gap-1"
            >
                {fills.map((fill, index) => (
                    <div
                        key={index}
                        className="h-1.5 flex-1 overflow-hidden rounded-full bg-border"
                    >
                        <div
                            className="h-full rounded-full bg-primary transition-[width] duration-150 ease-out motion-reduce:transition-none"
                            style={{ width: `${fill * 100}%` }}
                        />
                    </div>
                ))}
            </div>
            <p className="text-sm font-semibold" aria-hidden="true">
                {label}
            </p>
        </div>
    );
}

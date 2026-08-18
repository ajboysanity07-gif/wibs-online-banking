import { useCallback, useEffect, useRef, useState } from 'react';
import type {
    ApprovedDocumentPackageJob,
    ApprovedDocumentPackageJobStatus,
} from '@/lib/api/approved-document-package';

type ApprovedDocumentPackageApi = {
    dispatch: (
        loanRequestId: number,
    ) => Promise<Omit<ApprovedDocumentPackageJob, 'error_message'>>;
    status: (
        loanRequestId: number,
        packageJobId: number,
    ) => Promise<ApprovedDocumentPackageJob>;
    downloadUrl: (loanRequestId: number, packageJobId: number) => string;
};

const POLL_INTERVAL_MS = 2000;

export function useApprovedDocumentPackageDownload(
    loanRequestId: number,
    api: ApprovedDocumentPackageApi,
) {
    const [status, setStatus] = useState<
        ApprovedDocumentPackageJobStatus | 'idle'
    >('idle');
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const packageJobIdRef = useRef<number | null>(null);

    useEffect(() => {
        if (status !== 'queued' && status !== 'processing') {
            return;
        }

        const packageJobId = packageJobIdRef.current;

        if (packageJobId === null) {
            return;
        }

        let cancelled = false;
        const timeout = setTimeout(async () => {
            try {
                const job = await api.status(loanRequestId, packageJobId);

                if (cancelled) {
                    return;
                }

                setStatus(job.status);

                if (job.status === 'failed') {
                    setErrorMessage(
                        job.error_message ??
                            'Document package generation failed.',
                    );
                }

                if (job.status === 'completed') {
                    window.location.assign(
                        api.downloadUrl(loanRequestId, packageJobId),
                    );
                }
            } catch {
                if (!cancelled) {
                    setStatus('failed');
                    setErrorMessage('Unable to check document package status.');
                }
            }
        }, POLL_INTERVAL_MS);

        return () => {
            cancelled = true;
            clearTimeout(timeout);
        };
    }, [api, loanRequestId, status]);

    const start = useCallback(async () => {
        setErrorMessage(null);
        setStatus('queued');

        try {
            const job = await api.dispatch(loanRequestId);
            packageJobIdRef.current = job.id;
            setStatus(job.status);
        } catch {
            setStatus('failed');
            setErrorMessage('Unable to start document package generation.');
        }
    }, [api, loanRequestId]);

    return {
        status,
        isPreparing: status === 'queued' || status === 'processing',
        errorMessage,
        start,
    };
}

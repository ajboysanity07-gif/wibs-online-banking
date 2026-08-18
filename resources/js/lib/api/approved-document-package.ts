import client from '@/lib/api/client';
import adminPackageJobs from '@/routes/admin/requests/approved-documents/package-jobs';
import clientPackageJobs from '@/routes/client/loan-requests/approved-documents/package-jobs';
import staffPackageJobs from '@/routes/staff/loan-requests/approved-documents/package-jobs';

export type ApprovedDocumentPackageJobStatus =
    | 'queued'
    | 'processing'
    | 'completed'
    | 'failed';

export type ApprovedDocumentPackageJob = {
    id: number;
    status: ApprovedDocumentPackageJobStatus;
    error_message: string | null;
};

type PackageJobRoutes = {
    dispatch: (loanRequestId: number) => { url: string };
    status: (args: { loanRequest: number; packageJob: number }) => {
        url: string;
    };
    download: (args: { loanRequest: number; packageJob: number }) => {
        url: string;
    };
};

const buildApi = (routes: PackageJobRoutes) => ({
    async dispatch(
        loanRequestId: number,
    ): Promise<Omit<ApprovedDocumentPackageJob, 'error_message'>> {
        const response = await client.post<
            Omit<ApprovedDocumentPackageJob, 'error_message'>
        >(routes.dispatch(loanRequestId).url);

        return response.data;
    },
    async status(
        loanRequestId: number,
        packageJobId: number,
    ): Promise<ApprovedDocumentPackageJob> {
        const response = await client.get<ApprovedDocumentPackageJob>(
            routes.status({
                loanRequest: loanRequestId,
                packageJob: packageJobId,
            }).url,
        );

        return response.data;
    },
    downloadUrl(loanRequestId: number, packageJobId: number): string {
        return routes.download({
            loanRequest: loanRequestId,
            packageJob: packageJobId,
        }).url;
    },
});

export const adminApprovedDocumentPackageApi = buildApi(adminPackageJobs);
export const staffApprovedDocumentPackageApi = buildApi(staffPackageJobs);
export const memberApprovedDocumentPackageApi = buildApi(clientPackageJobs);

<?php

namespace App\Services\Admin;

use App\LoanRequestStatus;
use App\Models\LoanRequest;
use App\Models\LoanRequestCorrectionReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RequestsService
{
    public const UNAVAILABLE_MESSAGE = 'Requests module coming soon.';

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function getPreview(int $limit = 5): Collection
    {
        if (! $this->hasRequestsTable()) {
            return collect();
        }

        $query = $this->baseQuery();
        $this->applyOpenCorrectionReportMetadata($query);

        return $query
            ->limit($limit)
            ->get()
            ->map(fn (LoanRequest $request) => $this->mapRequest($request));
    }

    /**
     * @return array{
     *     items:\Illuminate\Support\Collection<int, array<string, mixed>>,
     *     available:bool,
     *     message:?string,
     *     paginator:?\Illuminate\Pagination\LengthAwarePaginator,
     *     loanTypes:array<int, string>,
     *     openCorrectionReports:int
     * }
     */
    public function getPaginated(
        string $search,
        int $perPage,
        int $page,
        ?string $loanType = null,
        ?string $status = null,
        ?float $minAmount = null,
        ?float $maxAmount = null,
        ?bool $reported = null,
    ): array {
        if (! $this->hasRequestsTable()) {
            return [
                'items' => collect(),
                'available' => false,
                'message' => self::UNAVAILABLE_MESSAGE,
                'paginator' => null,
                'loanTypes' => [],
                'openCorrectionReports' => 0,
            ];
        }

        $query = $this->baseQuery();
        $this->applyOpenCorrectionReportMetadata($query);

        $this->applyRequestsSearch($query, $search);

        if ($loanType !== null && $loanType !== '') {
            $query->where('loan_type_label_snapshot', $loanType);
        }

        if ($status !== null && $status !== '') {
            if ($status === LoanRequestStatus::UnderReview->value) {
                $query->whereIn('status', [
                    LoanRequestStatus::UnderReview->value,
                    LoanRequestStatus::Submitted->value,
                ]);
            } else {
                $query->where('status', $status);
            }
        }

        if ($minAmount !== null) {
            $query->where('requested_amount', '>=', $minAmount);
        }

        if ($maxAmount !== null) {
            $query->where('requested_amount', '<=', $maxAmount);
        }

        if ($reported !== null) {
            $this->applyReportedFilter($query, $reported);
        }

        $perPage = max(1, min($perPage, 50));
        $page = max(1, $page);
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => $paginator->getCollection()
                ->map(fn (LoanRequest $request) => $this->mapRequest($request)),
            'available' => true,
            'message' => null,
            'paginator' => $paginator,
            'loanTypes' => $this->getLoanTypeOptions(),
            'openCorrectionReports' => $this->countOpenCorrectionReports(),
        ];
    }

    /**
     * @return array{
     *     items:\Illuminate\Support\Collection<int, array<string, mixed>>,
     *     available:bool,
     *     message:?string,
     *     paginator:?\Illuminate\Pagination\LengthAwarePaginator,
     *     openCorrectionReports:int
     * }
     */
    public function getReportedPaginated(
        string $search,
        int $perPage,
        int $page,
    ): array {
        if (! $this->hasRequestsTable()) {
            return [
                'items' => collect(),
                'available' => false,
                'message' => self::UNAVAILABLE_MESSAGE,
                'paginator' => null,
                'openCorrectionReports' => 0,
            ];
        }

        $query = $this->baseQuery();
        $this->applyOpenCorrectionReportMetadata($query);
        $this->applyReportedFilter($query, true);
        $this->applyReportedSearch($query, $search);

        $query
            ->orderByDesc('latest_open_correction_reported_at')
            ->orderByDesc('submitted_at')
            ->orderByDesc('created_at');

        $perPage = max(1, min($perPage, 50));
        $page = max(1, $page);
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => $paginator->getCollection()
                ->map(fn (LoanRequest $request) => $this->mapReportedRequest($request)),
            'available' => true,
            'message' => null,
            'paginator' => $paginator,
            'openCorrectionReports' => $this->countOpenCorrectionReports(),
        ];
    }

    private function hasRequestsTable(): bool
    {
        return LoanRequest::query()->getConnection()->getSchemaBuilder()->hasTable('loan_requests');
    }

    private function baseQuery(): Builder
    {
        return LoanRequest::query()
            ->where('status', '!=', LoanRequestStatus::Draft->value)
            ->with([
                'applicant',
                'user',
                'latestOpenCorrectionReport.user',
            ])
            ->orderByDesc('submitted_at')
            ->orderByDesc('created_at');
    }

    /**
     * @return array<int, string>
     */
    private function getLoanTypeOptions(): array
    {
        if (! $this->hasRequestsTable()) {
            return [];
        }

        return LoanRequest::query()
            ->where('status', '!=', LoanRequestStatus::Draft->value)
            ->whereNotNull('loan_type_label_snapshot')
            ->where('loan_type_label_snapshot', '!=', '')
            ->select('loan_type_label_snapshot')
            ->distinct()
            ->orderBy('loan_type_label_snapshot')
            ->pluck('loan_type_label_snapshot')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRequest(LoanRequest $request): array
    {
        $status = $request->status instanceof LoanRequestStatus
            ? $request->status->value
            : (string) $request->status;

        if ($status === LoanRequestStatus::Submitted->value) {
            $status = LoanRequestStatus::UnderReview->value;
        }
        $submittedAt = $request->submitted_at?->toDateTimeString()
            ?? $request->created_at?->toDateTimeString();
        $applicant = $request->applicant;
        $memberName = $applicant
            ? trim(sprintf('%s %s', $applicant->first_name, $applicant->last_name))
            : null;
        $memberName = $memberName !== '' ? $memberName : ($request->user?->username ?? null);
        $latestOpenReport = $request->latestOpenCorrectionReport;
        $latestOpenReportReportedAt = $latestOpenReport?->created_at?->toDateTimeString()
            ?? $this->normalizeDateTime($request->latest_open_correction_reported_at ?? null);
        $hasOpenCorrectionReport = $latestOpenReport !== null;

        if (
            ! $hasOpenCorrectionReport
            && isset($request->has_open_correction_report)
        ) {
            $hasOpenCorrectionReport = (bool) $request->has_open_correction_report;
        }

        $summary = $request->loan_type_label_snapshot;

        return [
            'id' => $request->id,
            'reference' => $request->reference,
            'member_name' => $memberName,
            'status' => $status,
            'created_at' => $submittedAt,
            'summary' => $summary,
            'loan_type' => $request->loan_type_label_snapshot,
            'requested_amount' => $request->requested_amount,
            'submitted_at' => $submittedAt,
            'approved_amount' => $request->approved_amount,
            'reviewed_at' => $request->reviewed_at?->toDateTimeString(),
            'member_acctno' => $request->acctno,
            'has_open_correction_report' => $hasOpenCorrectionReport,
            'latest_correction_report_id' => $latestOpenReport?->id,
            'latest_correction_report_reported_at' => $latestOpenReportReportedAt,
            'latest_correction_report_issue' => $latestOpenReport?->issue_description,
            'latest_correction_report_correct_information' => $latestOpenReport?->correct_information,
            'latest_correction_report_supporting_note' => $latestOpenReport?->supporting_note,
            'latest_correction_report_reported_by' => $latestOpenReport?->user
                ? [
                    'user_id' => $latestOpenReport->user->user_id,
                    'name' => $latestOpenReport->user->name,
                    'acctno' => $latestOpenReport->user->acctno,
                ]
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapReportedRequest(LoanRequest $request): array
    {
        return $this->mapRequest($request);
    }

    private function applyOpenCorrectionReportMetadata(Builder $query): void
    {
        $query
            ->withExists([
                'correctionReports as has_open_correction_report' => function (
                    Builder $reportQuery,
                ): void {
                    $reportQuery->where(
                        'status',
                        LoanRequestCorrectionReport::STATUS_OPEN,
                    );
                },
            ])
            ->withMax([
                'correctionReports as latest_open_correction_reported_at' => function (
                    Builder $reportQuery,
                ): void {
                    $reportQuery->where(
                        'status',
                        LoanRequestCorrectionReport::STATUS_OPEN,
                    );
                },
            ], 'created_at');
    }

    private function applyReportedFilter(Builder $query, bool $reported): void
    {
        if ($reported) {
            $query->whereHas('correctionReports', function (
                Builder $reportQuery,
            ): void {
                $reportQuery->where(
                    'status',
                    LoanRequestCorrectionReport::STATUS_OPEN,
                );
            });

            return;
        }

        $query->whereDoesntHave('correctionReports', function (
            Builder $reportQuery,
        ): void {
            $reportQuery->where(
                'status',
                LoanRequestCorrectionReport::STATUS_OPEN,
            );
        });
    }

    private function applyRequestsSearch(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $referenceId = $this->parseReferenceId($search);

        $query->where(function (Builder $builder) use (
            $referenceId,
            $search,
        ): void {
            $builder
                ->where('acctno', 'like', "%{$search}%")
                ->orWhere('loan_type_label_snapshot', 'like', "%{$search}%")
                ->orWhere('typecode', 'like', "%{$search}%")
                ->orWhereHas('applicant', function (
                    Builder $applicantQuery,
                ) use ($search): void {
                    $applicantQuery
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%");
                });

            if ($referenceId !== null) {
                $builder->orWhereKey($referenceId);
            }
        });
    }

    private function applyReportedSearch(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $referenceId = $this->parseReferenceId($search);

        $query->where(function (Builder $builder) use (
            $referenceId,
            $search,
        ): void {
            $builder
                ->where('acctno', 'like', "%{$search}%")
                ->orWhereHas('applicant', function (
                    Builder $applicantQuery,
                ) use ($search): void {
                    $applicantQuery
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%");
                })
                ->orWhereHas('correctionReports', function (
                    Builder $reportQuery,
                ) use ($search): void {
                    $reportQuery
                        ->where(
                            'status',
                            LoanRequestCorrectionReport::STATUS_OPEN,
                        )
                        ->where(function (
                            Builder $searchQuery,
                        ) use ($search): void {
                            $searchQuery
                                ->where('issue_description', 'like', "%{$search}%")
                                ->orWhere('correct_information', 'like', "%{$search}%");
                        });
                });

            if ($referenceId !== null) {
                $builder->orWhereKey($referenceId);
            }
        });
    }

    private function parseReferenceId(string $search): ?int
    {
        $normalized = strtoupper(trim($search));

        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^LNREQ-(\d+)$/', $normalized, $matches) === 1) {
            return (int) $matches[1];
        }

        if (preg_match('/^\d+$/', $normalized) === 1) {
            return (int) $normalized;
        }

        return null;
    }

    private function countOpenCorrectionReports(): int
    {
        return LoanRequestCorrectionReport::query()
            ->where('status', LoanRequestCorrectionReport::STATUS_OPEN)
            ->count();
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}

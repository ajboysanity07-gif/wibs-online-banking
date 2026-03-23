<?php

namespace App\Services\Admin;

use App\LoanRequestStatus;
use App\Models\LoanRequest;
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

        return $this->baseQuery()
            ->limit($limit)
            ->get()
            ->map(fn (LoanRequest $request) => $this->mapRequest($request));
    }

    /**
     * @return array{
     *     items:\Illuminate\Support\Collection<int, array<string, mixed>>,
     *     available:bool,
     *     message:?string,
     *     paginator:?\Illuminate\Pagination\LengthAwarePaginator
     * }
     */
    public function getPaginated(string $search, int $perPage, int $page): array
    {
        if (! $this->hasRequestsTable()) {
            return [
                'items' => collect(),
                'available' => false,
                'message' => self::UNAVAILABLE_MESSAGE,
                'paginator' => null,
            ];
        }

        $query = $this->baseQuery();

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('acctno', 'like', "%{$search}%")
                    ->orWhere('loan_type_label_snapshot', 'like', "%{$search}%")
                    ->orWhere('typecode', 'like', "%{$search}%")
                    ->orWhereHas('applicant', function ($applicantQuery) use ($search) {
                        $applicantQuery
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('middle_name', 'like', "%{$search}%");
                    });
            });
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => $paginator->getCollection()
                ->map(fn (LoanRequest $request) => $this->mapRequest($request)),
            'available' => true,
            'message' => null,
            'paginator' => $paginator,
        ];
    }

    private function hasRequestsTable(): bool
    {
        return LoanRequest::query()->getConnection()->getSchemaBuilder()->hasTable('loan_requests');
    }

    private function baseQuery()
    {
        return LoanRequest::query()
            ->where('status', '!=', LoanRequestStatus::Draft->value)
            ->with(['applicant', 'user'])
            ->orderByDesc('submitted_at')
            ->orderByDesc('created_at');
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
        $summary = $request->loan_type_label_snapshot;

        return [
            'id' => $request->id,
            'member_name' => $memberName,
            'status' => $status,
            'created_at' => $submittedAt,
            'summary' => $summary,
            'loan_type' => $request->loan_type_label_snapshot,
            'requested_amount' => $request->requested_amount,
            'submitted_at' => $submittedAt,
        ];
    }
}

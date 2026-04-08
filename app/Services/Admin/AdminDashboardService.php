<?php

namespace App\Services\Admin;

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\Wmaster;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class AdminDashboardService
{
    /**
     * @return array{
     *     registeredCount:int,
     *     unregisteredCount:int,
     *     totalCount:int,
     *     requestsCount:?int,
     *     lastSync:?string
     * }
     */
    public function getMetrics(): array
    {
        if (! Schema::hasTable('wmaster')) {
            $registeredCount = AppUser::query()
                ->whereDoesntHave('adminProfile')
                ->count();

            return [
                'registeredCount' => $registeredCount,
                'unregisteredCount' => 0,
                'totalCount' => $registeredCount,
                'requestsCount' => $this->getPendingRequestsCount(),
                'lastSync' => 'Manual WIBS Desktop processing',
            ];
        }

        $countsBase = Wmaster::query()
            ->leftJoin('appusers', 'appusers.acctno', '=', 'wmaster.acctno')
            ->leftJoin('admin_profiles', 'admin_profiles.user_id', '=', 'appusers.user_id')
            ->whereNull('admin_profiles.user_id');

        $totalCount = (clone $countsBase)->count();
        $registeredCount = (clone $countsBase)
            ->whereNotNull('appusers.user_id')
            ->count();
        $unregisteredCount = max(0, $totalCount - $registeredCount);
        $requestsCount = $this->getPendingRequestsCount();

        return [
            'registeredCount' => $registeredCount,
            'unregisteredCount' => $unregisteredCount,
            'totalCount' => $totalCount,
            'requestsCount' => $requestsCount,
            'lastSync' => 'Manual WIBS Desktop processing',
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function getRecentRequestsPreview(int $limit = 5): Collection
    {
        if (! Schema::hasTable('loan_requests')) {
            return collect();
        }

        return LoanRequest::query()
            ->with('applicant')
            ->where('status', '!=', LoanRequestStatus::Draft->value)
            ->orderByDesc('submitted_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function (LoanRequest $request): array {
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
                $memberName = $memberName !== '' ? $memberName : null;

                return [
                    'id' => $request->id,
                    'reference' => $request->reference,
                    'member_name' => $memberName,
                    'status' => $status,
                    'created_at' => $submittedAt,
                    'summary' => $request->loan_type_label_snapshot,
                    'loan_type' => $request->loan_type_label_snapshot,
                    'requested_amount' => $request->requested_amount,
                    'submitted_at' => $submittedAt,
                ];
            });
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function getWatchlistPreview(string $type, int $limit = 5): Collection
    {
        return collect();
    }

    private function getPendingRequestsCount(): ?int
    {
        if (! Schema::hasTable('loan_requests')) {
            return null;
        }

        return LoanRequest::query()
            ->whereIn('status', [
                LoanRequestStatus::UnderReview->value,
                LoanRequestStatus::Submitted->value,
            ])
            ->count();
    }
}

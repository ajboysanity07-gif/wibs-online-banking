<?php

namespace App\Services\LoanRequests;

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\Wlntype;
use App\Support\SchemaCapabilities;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Read-only lookups for the loan-request catalog: loan type options and
 * per-member request summaries. Split out of LoanRequestService, which
 * owns creation/mutation, to keep the two concerns independently testable.
 */
class LoanRequestLookupService
{
    public function __construct(
        private SchemaCapabilities $schemaCapabilities,
    ) {}

    public function getLoanTypes(): Collection
    {
        return collect($this->getCachedLoanTypes());
    }

    /**
     * @return list<array{typecode: string, label: string}>
     */
    public function getCachedLoanTypes(): array
    {
        $hasLoanTypesTable = $this->schemaCapabilities->hasTable('wlntype');
        $hasLabelColumn = $this->schemaCapabilities->hasColumn('wlntype', 'lntype');

        if (! $hasLoanTypesTable || ! $hasLabelColumn) {
            return [];
        }

        $hasTypecode = $this->schemaCapabilities->hasColumn('wlntype', 'typecode');
        $columns = $hasTypecode ? ['typecode', 'lntype'] : ['lntype'];

        return Cache::remember(
            $this->loanTypesCacheKey(),
            now()->addMinutes(30),
            function () use ($columns, $hasTypecode): array {
                return Wlntype::query()
                    ->select($columns)
                    ->orderBy('lntype')
                    ->get()
                    ->map(function (Wlntype $type) use ($hasTypecode): array {
                        $label = (string) $type->lntype;
                        $typecode = $hasTypecode ? (string) $type->typecode : $label;

                        return [
                            'typecode' => $typecode,
                            'label' => $label,
                        ];
                    })
                    ->values()
                    ->all();
            },
        );
    }

    /**
     * @return list<array{
     *     id: int,
     *     status: string,
     *     typecode: string|null,
     *     loan_type_label_snapshot: string|null,
     *     requested_amount: string|float|int|null,
     *     requested_term: int|string|null,
     *     submitted_at: string|null,
     *     updated_at: string|null,
     *     assigned_officer: array{user_id: int, name: string, display_code: string|null}|null
     * }>
     */
    public function getMemberRequestSummaries(AppUser $user, int $limit = 10): array
    {
        if (! $this->schemaCapabilities->hasTable('loan_requests')) {
            return [];
        }

        $limit = max(1, min($limit, 50));

        return LoanRequest::query()
            ->where('user_id', $user->user_id)
            ->with('assignedOfficer.adminProfile')
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get([
                'id',
                'typecode',
                'loan_type_label_snapshot',
                'requested_amount',
                'requested_term',
                'status',
                'submitted_at',
                'updated_at',
                'assigned_officer_id',
            ])
            ->map(fn (LoanRequest $request): array => $this->serializeRequestSummary($request))
            ->all();
    }

    public function resolveLoanTypeLabel(string $typecode): string
    {
        $labels = $this->getLoanTypeLabelLookup();

        if (array_key_exists($typecode, $labels)) {
            return $labels[$typecode];
        }

        return $typecode;
    }

    /**
     * @return array<string, string>
     */
    private function getLoanTypeLabelLookup(): array
    {
        $hasLoanTypesTable = $this->schemaCapabilities->hasTable('wlntype');
        $hasLabelColumn = $this->schemaCapabilities->hasColumn('wlntype', 'lntype');

        if (! $hasLoanTypesTable || ! $hasLabelColumn) {
            return [];
        }

        return Cache::remember(
            $this->loanTypeLabelsCacheKey(),
            now()->addMinutes(30),
            function (): array {
                $labels = [];

                foreach ($this->getCachedLoanTypes() as $type) {
                    $labels[$type['typecode']] = $type['label'];
                }

                return $labels;
            },
        );
    }

    private function loanTypesCacheKey(): string
    {
        return 'loan_requests.loan_types';
    }

    private function loanTypeLabelsCacheKey(): string
    {
        return 'loan_requests.loan_type_labels';
    }

    /**
     * @return array{
     *     id: int,
     *     status: string,
     *     typecode: string|null,
     *     loan_type_label_snapshot: string|null,
     *     requested_amount: string|float|int|null,
     *     requested_term: int|string|null,
     *     submitted_at: string|null,
     *     updated_at: string|null,
     *     assigned_officer: array{user_id: int, name: string, display_code: string|null}|null
     * }
     */
    private function serializeRequestSummary(LoanRequest $loanRequest): array
    {
        $status = LoanRequestStatus::memberVisibleValue($loanRequest->status)
            ?? (string) $loanRequest->status;

        return [
            'id' => $loanRequest->id,
            'reference' => $loanRequest->reference,
            'status' => $status,
            'typecode' => $loanRequest->typecode,
            'loan_type_label_snapshot' => $loanRequest->loan_type_label_snapshot,
            'requested_amount' => $loanRequest->requested_amount,
            'requested_term' => $loanRequest->requested_term,
            'submitted_at' => $loanRequest->submitted_at?->toDateTimeString(),
            'updated_at' => $loanRequest->updated_at?->toDateTimeString(),
            'assigned_officer' => $loanRequest->assignedOfficer
                ? [
                    'user_id' => $loanRequest->assignedOfficer->user_id,
                    'name' => $loanRequest->assignedOfficer->adminProfile?->fullname
                        ?? $loanRequest->assignedOfficer->name,
                    'display_code' => $loanRequest->assignedOfficer->display_code,
                ]
                : null,
        ];
    }
}

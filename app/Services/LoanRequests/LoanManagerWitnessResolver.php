<?php

namespace App\Services\LoanRequests;

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestDataEntry;
use App\Models\Role;
use App\Models\StaffAccessControl;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resolves the loan managers eligible to appear as "Witness 2" on the
 * processing documents, plus the number of in-flight loans each one is
 * already attached to as the recorded witness.
 *
 * With a single active loan manager the name is auto-assigned at processing
 * time; with several, the loan processor picks one from a dropdown and that
 * choice is persisted as witness_two_name. Loan counts are scoped to the
 * non-terminal statuses so retired requests never inflate a manager's load.
 */
final class LoanManagerWitnessResolver
{
    /**
     * @return list<string>
     */
    private function terminalStatuses(): array
    {
        return [
            LoanRequestStatus::Rejected->value,
            LoanRequestStatus::Declined->value,
            LoanRequestStatus::MemberDeclinedTerms->value,
            LoanRequestStatus::Cancelled->value,
        ];
    }

    /**
     * @return list<AppUser>
     */
    public function managers(): array
    {
        return AppUser::query()
            ->whereHas('roles', function (Builder $query): void {
                $query->where('name', Role::LOAN_MANAGER);
            })
            ->where(function (Builder $query): void {
                $query
                    ->whereDoesntHave('staffAccessControl')
                    ->orWhereHas('staffAccessControl', function (Builder $controlQuery): void {
                        $controlQuery->where('status', StaffAccessControl::STATUS_ACTIVE);
                    });
            })
            ->orderBy('username')
            ->orderBy('email')
            ->get()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, active_loans: int}>
     */
    public function options(): array
    {
        return array_map(
            fn (AppUser $manager): array => $this->optionFor($manager),
            $this->managers(),
        );
    }

    /**
     * @return array{id: int, name: string, active_loans: int}
     */
    public function optionFor(AppUser $manager): array
    {
        return [
            'id' => $manager->user_id,
            'name' => $manager->resolvedDisplayName() ?? $manager->username,
            'active_loans' => $this->activeLoanCount($manager->resolvedDisplayName()),
        ];
    }

    /**
     * Number of in-flight (non-terminal) loan requests where the manager is
     * currently the recorded witness_two_name. Entries are filtered in PHP
     * because value_json is a TEXT column with an array cast, so JSON-path
     * operators are not portable across the supported database drivers.
     */
    public function activeLoanCount(?string $displayName): int
    {
        if ($displayName === null || trim($displayName) === '') {
            return 0;
        }

        $inFlightRequestIds = LoanRequest::query()
            ->whereNotIn('status', $this->terminalStatuses())
            ->select('id');

        $entries = LoanRequestDataEntry::query()
            ->where('field_key', 'witness_two_name')
            ->whereIn('loan_request_id', $inFlightRequestIds)
            ->get();

        return $entries
            ->filter(
                fn (LoanRequestDataEntry $entry): bool => ($entry->value_json['value'] ?? null) === $displayName,
            )
            ->count();
    }
}

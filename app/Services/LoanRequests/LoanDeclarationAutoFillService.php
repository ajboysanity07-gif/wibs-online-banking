<?php

namespace App\Services\LoanRequests;

use App\Models\AppUser;
use App\Models\Wlnmaster;
use App\Support\SchemaCapabilities;
use DateTimeInterface;
use Illuminate\Support\Collection;

class LoanDeclarationAutoFillService
{
    private const STATUS_LABELS = [
        'ACT' => 'Active',
        'PDL' => 'Past Due',
        'IIL' => 'In Litigation',
    ];

    public function __construct(
        private SchemaCapabilities $schemaCapabilities,
    ) {}

    /**
     * Auto-fill values for the member loan-request wizard declarations
     * section. Only used on fresh forms -- never overrides saved drafts.
     *
     * @return array<string, bool|string|float|null>
     */
    public function getDeclarationData(AppUser $user): array
    {
        $acctno = $this->normalizeAcctno($user->acctno);

        if ($acctno === null || ! $this->hasWlnmaster()) {
            return $this->emptyDeclarationData();
        }

        $hasExistingLoans = $this->hasExistingLoans($acctno);
        $hasLitigationLoans = $this->hasColumn('lnstatus')
            ? $this->hasLitigationLoans($acctno)
            : false;
        $mostRecent = $this->getMostRecentLoanDetails($acctno);

        return [
            'declaration_existing_loans' => $hasExistingLoans,
            'declaration_pending_cases' => $hasLitigationLoans,
            'existing_loan_1_date' => $mostRecent['existing_loan_1_date'] ?? null,
            'existing_loan_1_type' => $mostRecent['existing_loan_1_type'] ?? null,
            'existing_loan_1_amount' => $mostRecent['existing_loan_1_amount'] ?? null,
        ];
    }

    /**
     * Summary used by staff/admin review pages to surface PDL/IIL risk.
     *
     * @return array{
     *     has_active: bool,
     *     has_past_due: bool,
     *     has_litigation: bool,
     *     total_active: int,
     *     total_past_due: int,
     *     total_litigation: int,
     *     active_balance_total: float,
     *     past_due_balance_total: float,
     *     litigation_balance_total: float,
     *     requires_attention: bool,
     *     warning_message: string|null
     * }
     */
    public function getLoanStatusSummaryForStaff(string $acctno): array
    {
        $acctno = $this->normalizeAcctno($acctno);

        if ($acctno === null || ! $this->hasWlnmaster()) {
            return $this->emptyStaffSummary();
        }

        $loans = $this->allLoansForAcctno($acctno);
        $active = $loans->filter(fn (Wlnmaster $loan): bool => $this->statusIs($loan, 'ACT'));
        $pastDue = $loans->filter(fn (Wlnmaster $loan): bool => $this->statusIs($loan, 'PDL'));
        $litigation = $loans->filter(fn (Wlnmaster $loan): bool => $this->statusIs($loan, 'IIL'));

        $summary = [
            'has_active' => $active->isNotEmpty(),
            'has_past_due' => $pastDue->isNotEmpty(),
            'has_litigation' => $litigation->isNotEmpty(),
            'total_active' => $active->count(),
            'total_past_due' => $pastDue->count(),
            'total_litigation' => $litigation->count(),
            'active_balance_total' => $this->sumBalances($active),
            'past_due_balance_total' => $this->sumBalances($pastDue),
            'litigation_balance_total' => $this->sumBalances($litigation),
        ];

        $summary['requires_attention'] = $pastDue->isNotEmpty() || $litigation->isNotEmpty();
        $summary['warning_message'] = $this->buildWarningMessage($pastDue->count(), $litigation->count());

        return $summary;
    }

    /**
     * Detailed PDL/IIL loans for the staff review page. IIL loans first,
     * then ordered by most recent release date.
     *
     * @return list<array<string, mixed>>
     */
    public function getProblemLoans(string $acctno): array
    {
        $acctno = $this->normalizeAcctno($acctno);

        if ($acctno === null || ! $this->hasWlnmaster() || ! $this->hasColumn('lnstatus')) {
            return [];
        }

        return Wlnmaster::query()
            ->where('acctno', $acctno)
            ->whereIn('lnstatus', ['PDL', 'IIL'])
            ->orderByRaw("CASE lnstatus WHEN 'IIL' THEN 0 ELSE 1 END")
            ->orderByDesc($this->resolveDateColumn())
            ->orderByDesc('lnnumber')
            ->get()
            ->map(fn (Wlnmaster $loan): array => $this->serializeProblemLoan($loan))
            ->values()
            ->all();
    }

    /**
     * Summary for the member dashboard, grouping all loans by status.
     *
     * @return array<string, mixed>
     */
    public function getLoanStatusSummaryForMember(string $acctno): array
    {
        $acctno = $this->normalizeAcctno($acctno);

        if ($acctno === null || ! $this->hasWlnmaster()) {
            return $this->emptyMemberSummary();
        }

        $loans = $this->allLoansForAcctno($acctno);
        $active = $loans->filter(fn (Wlnmaster $loan): bool => $this->statusIs($loan, 'ACT'));
        $pastDue = $loans->filter(fn (Wlnmaster $loan): bool => $this->statusIs($loan, 'PDL'));
        $litigation = $loans->filter(fn (Wlnmaster $loan): bool => $this->statusIs($loan, 'IIL'));

        return [
            'total_loans' => $loans->count(),
            'active_count' => $active->count(),
            'past_due_count' => $pastDue->count(),
            'litigation_count' => $litigation->count(),
            'total_balance' => $this->sumBalances($loans),
            'active_balance' => $this->sumBalances($active),
            'past_due_balance' => $this->sumBalances($pastDue),
            'litigation_balance' => $this->sumBalances($litigation),
            'loans' => $loans
                ->map(fn (Wlnmaster $loan): array => $this->serializeProblemLoan($loan))
                ->values()
                ->all(),
        ];
    }

    private function hasExistingLoans(string $acctno): bool
    {
        return Wlnmaster::query()
            ->where('acctno', $acctno)
            ->exists();
    }

    private function hasLitigationLoans(string $acctno): bool
    {
        return Wlnmaster::query()
            ->where('acctno', $acctno)
            ->where('lnstatus', 'IIL')
            ->exists();
    }

    /**
     * @return array{existing_loan_1_date: string|null, existing_loan_1_type: string|null, existing_loan_1_amount: float|null}|null
     */
    private function getMostRecentLoanDetails(string $acctno): ?array
    {
        $loan = Wlnmaster::query()
            ->where('acctno', $acctno)
            ->orderByDesc($this->resolveDateColumn())
            ->orderByDesc('lnnumber')
            ->first();

        if ($loan === null) {
            return null;
        }

        return [
            'existing_loan_1_date' => $this->formatDate($loan->{$this->resolveDateColumn()}),
            'existing_loan_1_type' => $this->normalizeOptionalString($loan->lntype),
            'existing_loan_1_amount' => $this->normalizeDecimal($loan->principal),
        ];
    }

    private function resolveDateColumn(): string
    {
        return $this->hasColumn('date_rel')
            ? 'date_rel'
            : ($this->hasColumn('lastmove') ? 'lastmove' : 'lnnumber');
    }

    private function getLoanStatusLabel(string $status): string
    {
        $normalized = strtoupper(trim((string) $status));

        return self::STATUS_LABELS[$normalized] ?? $normalized;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Wlnmaster>
     */
    private function allLoansForAcctno(string $acctno): Collection
    {
        return Wlnmaster::query()
            ->where('acctno', $acctno)
            ->orderByDesc($this->resolveDateColumn())
            ->orderByDesc('lnnumber')
            ->get();
    }

    private function statusIs(Wlnmaster $loan, string $status): bool
    {
        return strtoupper(trim((string) $loan->lnstatus)) === $status;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Wlnmaster>  $loans
     */
    private function sumBalances(Collection $loans): float
    {
        return round($loans->sum(fn (Wlnmaster $loan): float => (float) ($loan->balance ?? 0)), 2);
    }

    private function buildWarningMessage(int $pastDueCount, int $litigationCount): ?string
    {
        $parts = [];

        if ($pastDueCount > 0) {
            $parts[] = sprintf('%d past due loan(s)', $pastDueCount);
        }

        if ($litigationCount > 0) {
            $parts[] = sprintf('%d loan(s) in litigation', $litigationCount);
        }

        if ($parts === []) {
            return null;
        }

        return 'Applicant has '.implode(' and ', $parts);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProblemLoan(Wlnmaster $loan): array
    {
        $status = strtoupper(trim((string) $loan->lnstatus));

        return [
            'lnnumber' => (string) $loan->lnnumber,
            'lntype' => $this->normalizeOptionalString($loan->lntype) ?? '',
            'lnstatus' => $status,
            'lnstatus_label' => $this->getLoanStatusLabel($status),
            'principal' => $this->normalizeDecimal($loan->principal) ?? 0.0,
            'balance' => $this->normalizeDecimal($loan->balance) ?? 0.0,
            'date_rel' => $this->formatDate($loan->date_rel),
            'date_mat' => $this->formatDate($loan->date_mat),
        ];
    }

    /**
     * @return array<string, bool|string|float|null>
     */
    private function emptyDeclarationData(): array
    {
        return [
            'declaration_existing_loans' => false,
            'declaration_pending_cases' => false,
            'existing_loan_1_date' => null,
            'existing_loan_1_type' => null,
            'existing_loan_1_amount' => null,
        ];
    }

    /**
     * @return array<string, bool|float|string|null>
     */
    private function emptyStaffSummary(): array
    {
        return [
            'has_active' => false,
            'has_past_due' => false,
            'has_litigation' => false,
            'total_active' => 0,
            'total_past_due' => 0,
            'total_litigation' => 0,
            'active_balance_total' => 0.0,
            'past_due_balance_total' => 0.0,
            'litigation_balance_total' => 0.0,
            'requires_attention' => false,
            'warning_message' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyMemberSummary(): array
    {
        return [
            'total_loans' => 0,
            'active_count' => 0,
            'past_due_count' => 0,
            'litigation_count' => 0,
            'total_balance' => 0.0,
            'active_balance' => 0.0,
            'past_due_balance' => 0.0,
            'litigation_balance' => 0.0,
            'loans' => [],
        ];
    }

    private function hasWlnmaster(): bool
    {
        return $this->schemaCapabilities->hasTable('wlnmaster');
    }

    private function hasColumn(string $column): bool
    {
        return $this->schemaCapabilities->hasColumn('wlnmaster', $column);
    }

    private function normalizeAcctno(mixed $acctno): ?string
    {
        if ($acctno === null) {
            return null;
        }

        $trimmed = trim((string) $acctno);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function normalizeDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function formatDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        $candidate = substr($trimmed, 0, 10);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $candidate) === 1
            ? $candidate
            : null;
    }
}

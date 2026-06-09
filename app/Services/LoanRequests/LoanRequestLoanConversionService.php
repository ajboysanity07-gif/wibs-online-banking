<?php

namespace App\Services\LoanRequests;

use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestChange;
use App\Models\Wlnled;
use App\Models\Wlnmaster;
use App\Models\Wlntype;
use App\Support\SchemaCapabilities;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class LoanRequestLoanConversionService
{
    private const ACTIVE_LOAN_STATUS = 'ACT';

    private const DEFAULT_INSTALLMENT = 'MONTHLY';

    private const RELEASE_LEDGER_CODE = 'RL';

    private const RELEASE_LEDGER_CASH_CHECK = 'CS';

    public function __construct(
        private SchemaCapabilities $schemaCapabilities,
    ) {}

    /**
     * @return array{
     *     loan_id: string,
     *     loan_number: string,
     *     loan_status: string,
     *     ledger_control_no: string|null,
     *     ledger_trans_no: string|null
     * }
     */
    public function createLoanForApprovedRequest(
        LoanRequest $loanRequest,
        AppUser $actor,
    ): array {
        if (! $this->schemaCapabilities->hasTable('wlnmaster')) {
            throw ValidationException::withMessages([
                'loan' => 'Loan conversion is unavailable until the loan master table is available.',
            ]);
        }

        $this->ensureLoanRequestHasNotBeenConverted($loanRequest);

        $loanRequest->loadMissing('user', 'applicant');

        if ($this->schemaCapabilities->hasTable('wmaster')) {
            $loanRequest->user?->loadMissing('wmaster');
        }

        $acctno = trim((string) ($loanRequest->acctno ?? $loanRequest->user?->acctno ?? ''));

        if ($acctno === '') {
            throw ValidationException::withMessages([
                'loan_request' => 'Loan request is missing a member account number.',
            ]);
        }

        $approvedAmount = $this->resolveApprovedAmount($loanRequest);
        $approvedTerm = $this->resolveApprovedTerm($loanRequest);
        $approvedInterestRate = $this->resolveApprovedInterestRate($loanRequest);
        $convertedAt = now()->startOfSecond();
        $typecode = trim((string) ($loanRequest->typecode ?? ''));
        $loanTypeLabel = $this->resolveLoanTypeLabel($loanRequest);
        $loanNumber = $this->nextLoanNumber($typecode);

        $legacyLoan = new Wlnmaster;
        $legacyLoan->forceFill($this->buildMasterPayload(
            $loanRequest,
            $acctno,
            $loanNumber,
            $typecode,
            $loanTypeLabel,
            $approvedAmount,
            $approvedTerm,
            $approvedInterestRate,
            $convertedAt,
        ));
        $legacyLoan->save();

        $ledgerMetadata = [
            'ledger_control_no' => null,
            'ledger_trans_no' => null,
        ];

        if ($this->schemaCapabilities->hasTable('wlnled')) {
            $ledgerMetadata = $this->createInitialLedgerEntry(
                $loanRequest,
                $actor,
                $acctno,
                $loanNumber,
                $typecode,
                $loanTypeLabel,
                $approvedAmount,
                $convertedAt,
            );
        }

        return [
            'loan_id' => $loanNumber,
            'loan_number' => $loanNumber,
            'loan_status' => self::ACTIVE_LOAN_STATUS,
            'ledger_control_no' => $ledgerMetadata['ledger_control_no'],
            'ledger_trans_no' => $ledgerMetadata['ledger_trans_no'],
        ];
    }

    private function resolveApprovedAmount(LoanRequest $loanRequest): float
    {
        $amount = $loanRequest->approved_amount;

        if ($amount === null || ! is_numeric($amount) || (float) $amount <= 0) {
            throw ValidationException::withMessages([
                'approved_amount' => 'Approved amount is required before a loan can be converted.',
            ]);
        }

        return round((float) $amount, 2);
    }

    private function resolveApprovedTerm(LoanRequest $loanRequest): int
    {
        $term = $loanRequest->approved_term;

        if ($term === null || ! is_numeric($term) || (int) $term < 1) {
            throw ValidationException::withMessages([
                'approved_term' => 'Approved term is required before a loan can be converted.',
            ]);
        }

        return (int) $term;
    }

    private function resolveApprovedInterestRate(LoanRequest $loanRequest): float
    {
        $interestRate = $loanRequest->approved_interest_rate;

        if ($interestRate === null || $interestRate === '') {
            return 0.0;
        }

        if (! is_numeric($interestRate)) {
            throw ValidationException::withMessages([
                'approved_interest_rate' => 'Approved interest rate must be numeric before a loan can be converted.',
            ]);
        }

        return round((float) $interestRate, 4);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMasterPayload(
        LoanRequest $loanRequest,
        string $acctno,
        string $loanNumber,
        string $typecode,
        string $loanTypeLabel,
        float $approvedAmount,
        int $approvedTerm,
        float $approvedInterestRate,
        CarbonInterface $convertedAt,
    ): array {
        $termDays = $approvedTerm * 30;
        $maturityDate = $convertedAt->copy()->addDays($termDays);
        $remarks = $this->conversionRemarks($loanRequest);

        return $this->filterColumns('wlnmaster', [
            'acctno' => $acctno,
            'lnnumber' => $loanNumber,
            'loan_request_id' => $loanRequest->id,
            'typecode' => $typecode !== '' ? $typecode : null,
            'lntype' => $loanTypeLabel,
            'lnstatus' => self::ACTIVE_LOAN_STATUS,
            'principal' => $approvedAmount,
            'balance' => $approvedAmount,
            'lastmove' => $convertedAt,
            'date_in' => $convertedAt,
            'date_start' => $convertedAt,
            'date_mat' => $maturityDate,
            'date_rel' => $convertedAt,
            'int_rate' => $approvedInterestRate,
            'term_mons' => $termDays,
            'amortization' => $this->calculatePeriodicAmortization(
                $approvedAmount,
                $approvedTerm,
                $approvedInterestRate,
            ),
            'installment' => self::DEFAULT_INSTALLMENT,
            'purpose' => $this->normalizeOptionalText($loanRequest->loan_purpose)
                ?? $loanTypeLabel,
            'remarks' => $remarks,
            'approved_by' => $loanRequest->approved_by,
            'initial' => $approvedAmount,
        ]);
    }

    /**
     * @return array{ledger_control_no: string|null, ledger_trans_no: string|null}
     */
    private function createInitialLedgerEntry(
        LoanRequest $loanRequest,
        AppUser $actor,
        string $acctno,
        string $loanNumber,
        string $typecode,
        string $loanTypeLabel,
        float $approvedAmount,
        CarbonInterface $convertedAt,
    ): array {
        $controlNo = $this->nextLedgerSequence('controlno');
        $transNo = $this->nextLedgerSequence('transno');

        $ledger = new Wlnled;
        $ledger->forceFill($this->filterColumns('wlnled', [
            'lnstatus' => self::ACTIVE_LOAN_STATUS,
            'acctno' => $acctno,
            'lnnumber' => $loanNumber,
            'bname' => $this->resolveMemberName($loanRequest),
            'typecode' => $typecode !== '' ? $typecode : null,
            'lntype' => $loanTypeLabel,
            'date_in' => $convertedAt,
            'mreference' => $loanRequest->reference,
            'cs_ck' => self::RELEASE_LEDGER_CASH_CHECK,
            'lncode' => self::RELEASE_LEDGER_CODE,
            'principal' => $approvedAmount,
            'payments' => 0,
            'balance' => $approvedAmount,
            'debit' => 0,
            'credit' => 0,
            'unsettled' => 0,
            'transno' => $transNo,
            'controlno' => $controlNo,
            'initial' => $this->resolveActorInitials($actor),
        ]));
        $ledger->save();

        return [
            'ledger_control_no' => $controlNo,
            'ledger_trans_no' => $transNo,
        ];
    }

    private function nextLoanNumber(string $typecode): string
    {
        $prefix = $this->resolveLoanNumberPrefix($typecode);
        $latestLoanNumber = Wlnmaster::query()
            ->where('lnnumber', 'like', $prefix.'-%')
            ->orderByDesc('lnnumber')
            ->lockForUpdate()
            ->value('lnnumber');

        $nextSequence = $this->nextSequenceFromLoanNumber($latestLoanNumber);
        $loanNumber = sprintf('%s-%06d', $prefix, $nextSequence);

        while (Wlnmaster::query()->where('lnnumber', $loanNumber)->exists()) {
            $nextSequence++;
            $loanNumber = sprintf('%s-%06d', $prefix, $nextSequence);
        }

        return $loanNumber;
    }

    private function ensureLoanRequestHasNotBeenConverted(
        LoanRequest $loanRequest,
    ): void {
        if (
            $this->schemaCapabilities->hasColumn('wlnmaster', 'loan_request_id')
            && Wlnmaster::query()
                ->where('loan_request_id', $loanRequest->id)
                ->lockForUpdate()
                ->exists()
        ) {
            $this->throwAlreadyConverted();
        }

        if (
            $this->schemaCapabilities->hasColumn('wlnmaster', 'remarks')
            && Wlnmaster::query()
                ->where('remarks', $this->conversionRemarks($loanRequest))
                ->lockForUpdate()
                ->exists()
        ) {
            $this->throwAlreadyConverted();
        }

        if (
            $this->schemaCapabilities->hasTable('loan_request_changes')
            && LoanRequestChange::query()
                ->where('loan_request_id', $loanRequest->id)
                ->where('action', LoanRequestChange::ACTION_CONVERT_TO_LOAN)
                ->lockForUpdate()
                ->exists()
        ) {
            $this->throwAlreadyConverted();
        }
    }

    private function resolveLoanNumberPrefix(string $typecode): string
    {
        $digits = preg_replace('/\D+/', '', trim($typecode)) ?? '';

        if ($digits === '') {
            return '0199';
        }

        $normalizedDigits = strlen($digits) <= 2
            ? str_pad($digits, 2, '0', STR_PAD_LEFT)
            : substr($digits, -2);

        return '01'.$normalizedDigits;
    }

    private function nextSequenceFromLoanNumber(mixed $loanNumber): int
    {
        $normalizedLoanNumber = trim((string) $loanNumber);

        if ($normalizedLoanNumber === '' || ! str_contains($normalizedLoanNumber, '-')) {
            return 1;
        }

        [$prefix, $suffix] = explode('-', $normalizedLoanNumber, 2);
        $numericSuffix = preg_replace('/\D+/', '', $suffix) ?? '';

        if ($numericSuffix === '') {
            return 1;
        }

        return ((int) $numericSuffix) + 1;
    }

    private function nextLedgerSequence(string $column): ?string
    {
        if (! $this->schemaCapabilities->hasColumn('wlnled', $column)) {
            return null;
        }

        $currentMax = Wlnled::query()
            ->lockForUpdate()
            ->max($column);

        $numericValue = preg_replace('/\D+/', '', trim((string) $currentMax)) ?? '';
        $nextValue = $numericValue === ''
            ? 1
            : ((int) $numericValue) + 1;

        return (string) $nextValue;
    }

    private function resolveLoanTypeLabel(LoanRequest $loanRequest): string
    {
        $label = $this->normalizeOptionalText(
            $loanRequest->loan_type_label_snapshot,
        );

        if ($label !== null) {
            return $label;
        }

        $typecode = $this->normalizeOptionalText($loanRequest->typecode);

        if (
            $typecode !== null
            && $this->schemaCapabilities->hasTable('wlntype')
            && $this->schemaCapabilities->hasColumn('wlntype', 'lntype')
        ) {
            $resolvedLabel = $this->normalizeOptionalText(
                Wlntype::query()
                    ->whereKey($typecode)
                    ->value('lntype'),
            );

            if ($resolvedLabel !== null) {
                return $resolvedLabel;
            }
        }

        return $typecode ?? 'Converted Loan';
    }

    private function resolveMemberName(LoanRequest $loanRequest): ?string
    {
        $memberName = $loanRequest->user?->wmaster?->displayName();

        if (is_string($memberName) && trim($memberName) !== '') {
            return trim($memberName);
        }

        $applicant = $loanRequest->applicant;

        if ($applicant !== null) {
            $applicantName = trim(sprintf(
                '%s %s',
                trim((string) $applicant->first_name),
                trim((string) $applicant->last_name),
            ));

            if ($applicantName !== '') {
                return $applicantName;
            }
        }

        $fallbackName = trim((string) ($loanRequest->user?->name ?? ''));

        return $fallbackName !== '' ? $fallbackName : null;
    }

    private function resolveActorInitials(AppUser $actor): ?string
    {
        $source = trim((string) ($actor->adminProfile?->fullname ?? $actor->name));

        if ($source === '') {
            return null;
        }

        $parts = preg_split('/\s+/', $source) ?: [];
        $initials = collect($parts)
            ->map(static fn (string $part): string => strtoupper(substr($part, 0, 1)))
            ->implode('');

        $initials = substr($initials, 0, 10);

        return $initials !== '' ? $initials : null;
    }

    private function calculatePeriodicAmortization(
        float $approvedAmount,
        int $approvedTerm,
        float $approvedInterestRate,
    ): float {
        if ($approvedTerm < 1) {
            return 0.0;
        }

        if ($approvedInterestRate <= 0) {
            return round($approvedAmount / $approvedTerm, 2);
        }

        $periodicInterestRate = ($approvedInterestRate / 100) / 12;

        if ($periodicInterestRate <= 0) {
            return round($approvedAmount / $approvedTerm, 2);
        }

        $payment = $approvedAmount
            * ($periodicInterestRate / (1 - ((1 + $periodicInterestRate) ** (-$approvedTerm))));

        return round($payment, 2);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function filterColumns(string $table, array $attributes): array
    {
        $filtered = [];

        foreach ($attributes as $column => $value) {
            if ($this->schemaCapabilities->hasColumn($table, $column)) {
                $filtered[$column] = $value;
            }
        }

        return $filtered;
    }

    private function normalizeOptionalText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalizedValue = trim((string) $value);

        return $normalizedValue !== '' ? $normalizedValue : null;
    }

    private function conversionRemarks(LoanRequest $loanRequest): string
    {
        return sprintf('Converted from %s', $loanRequest->reference);
    }

    private function throwAlreadyConverted(): never
    {
        throw ValidationException::withMessages([
            'loan_request' => 'This loan request has already been converted to an actual loan.',
        ]);
    }
}

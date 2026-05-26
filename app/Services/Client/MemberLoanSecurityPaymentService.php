<?php

namespace App\Services\Client;

use App\Models\AppUser;
use App\Models\Wlnled;
use App\Models\Wlnmaster;
use App\Models\Wsavled;
use App\Models\Wsvmaster;
use App\Support\SchemaCapabilities;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MemberLoanSecurityPaymentService
{
    private const LOAN_SECURITY_TYPECODE = '01';

    private const MINIMUM_SECURITY_BALANCE = 500.0;

    public function __construct(
        private SchemaCapabilities $schemaCapabilities,
    ) {}

    /**
     * @return array{
     *     appliedAmount: float,
     *     loanBalance: float,
     *     securityBalance: float
     * }
     */
    public function pay(
        AppUser $user,
        string $loanNumber,
        float $requestedAmount,
    ): array {
        $acctno = $this->resolveAcctno($user);
        $loanNumber = trim($loanNumber);

        if ($loanNumber === '') {
            abort(404);
        }

        return DB::transaction(function () use ($acctno, $loanNumber, $requestedAmount): array {
            $loan = $this->findLoanForUpdate($acctno, $loanNumber);
            $security = $this->findSecurityForUpdate($acctno);

            $loanBalance = round((float) ($loan->balance ?? 0), 2);

            if ($loanBalance <= 0) {
                throw $this->amountException('This loan has no remaining balance.');
            }

            $securityBalance = $this->resolveSecurityBalance($security);
            $maxPayable = round(max(0, $securityBalance - self::MINIMUM_SECURITY_BALANCE), 2);

            if ($requestedAmount > $maxPayable) {
                throw $this->amountException(sprintf(
                    'The maximum payable from loan security is %s to keep at least %s remaining.',
                    $this->formatAmount($maxPayable),
                    $this->formatAmount(self::MINIMUM_SECURITY_BALANCE),
                ));
            }

            $appliedAmount = round(min($requestedAmount, $loanBalance), 2);

            if ($appliedAmount <= 0) {
                throw $this->amountException('This loan has no remaining balance.');
            }

            $remainingLoanBalance = round($loanBalance - $appliedAmount, 2);
            $remainingSecurityBalance = round($securityBalance - $appliedAmount, 2);
            $movedAt = now();
            $reference = 'SEC-LOAN-'.$movedAt->format('YmdHisv');

            $this->updateLoan($loan, $remainingLoanBalance, $movedAt);
            $this->updateSecurity($security, $remainingSecurityBalance, $movedAt);
            $this->insertLoanLedger(
                $loan,
                $appliedAmount,
                $remainingLoanBalance,
                $movedAt,
                $reference,
            );
            $this->insertSecurityLedger(
                $security,
                $appliedAmount,
                $remainingSecurityBalance,
                $movedAt,
            );

            return [
                'appliedAmount' => $appliedAmount,
                'loanBalance' => $remainingLoanBalance,
                'securityBalance' => $remainingSecurityBalance,
            ];
        });
    }

    /**
     * @return array{
     *     svnumber: ?string,
     *     currentBalance: float,
     *     minimumBalance: float,
     *     maxPayable: float
     * }
     */
    public function getPaymentPanelData(AppUser $user): array
    {
        $security = $this->findSecurityForDisplay(
            $this->resolveAcctno($user),
        );

        if ($security === null) {
            return $this->defaultPaymentPanelData();
        }

        $currentBalance = $this->resolveSecurityBalance($security);

        return [
            'svnumber' => $security->svnumber !== null
                ? (string) $security->svnumber
                : null,
            'currentBalance' => $currentBalance,
            'minimumBalance' => self::MINIMUM_SECURITY_BALANCE,
            'maxPayable' => round(
                max(0, $currentBalance - self::MINIMUM_SECURITY_BALANCE),
                2,
            ),
        ];
    }

    private function resolveAcctno(AppUser $user): string
    {
        $acctno = trim((string) $user->acctno);

        if ($acctno === '') {
            abort(404);
        }

        return $acctno;
    }

    private function findLoanForUpdate(string $acctno, string $loanNumber): Wlnmaster
    {
        if (! $this->hasTable('wlnmaster')) {
            abort(404);
        }

        $loan = Wlnmaster::query()
            ->where('acctno', $acctno)
            ->where('lnnumber', $loanNumber)
            ->lockForUpdate()
            ->first();

        if ($loan === null) {
            abort(404);
        }

        return $loan;
    }

    private function findSecurityForUpdate(string $acctno): Wsvmaster
    {
        if (
            ! $this->hasTable('wsvmaster')
            || ! $this->hasColumn('wsvmaster', 'typecode')
        ) {
            throw $this->amountException('Loan security is not available for this payment.');
        }

        $security = Wsvmaster::query()
            ->where('acctno', $acctno)
            ->where('typecode', self::LOAN_SECURITY_TYPECODE)
            ->orderBy('svnumber')
            ->lockForUpdate()
            ->first();

        if ($security === null) {
            throw $this->amountException('Loan security is not available for this payment.');
        }

        return $security;
    }

    private function findSecurityForDisplay(string $acctno): ?Wsvmaster
    {
        if (
            ! $this->hasTable('wsvmaster')
            || ! $this->hasColumn('wsvmaster', 'typecode')
        ) {
            return null;
        }

        return Wsvmaster::query()
            ->where('acctno', $acctno)
            ->where('typecode', self::LOAN_SECURITY_TYPECODE)
            ->orderBy('svnumber')
            ->first();
    }

    private function resolveSecurityBalance(Wsvmaster $security): float
    {
        if ($this->hasColumn('wsvmaster', 'wbalance')) {
            return round((float) ($security->wbalance ?? 0), 2);
        }

        if ($this->hasColumn('wsvmaster', 'balance')) {
            return round((float) ($security->balance ?? 0), 2);
        }

        return 0.0;
    }

    private function updateLoan(
        Wlnmaster $loan,
        float $remainingLoanBalance,
        DateTimeInterface $movedAt,
    ): void {
        $updates = [];

        if ($this->hasColumn('wlnmaster', 'balance')) {
            $updates['balance'] = $remainingLoanBalance;
        }

        if ($this->hasColumn('wlnmaster', 'lastmove')) {
            $updates['lastmove'] = $movedAt;
        }

        if ($updates !== []) {
            $loan->forceFill($updates)->save();
        }
    }

    private function updateSecurity(
        Wsvmaster $security,
        float $remainingSecurityBalance,
        DateTimeInterface $movedAt,
    ): void {
        $updates = [];

        if ($this->hasColumn('wsvmaster', 'balance')) {
            $updates['balance'] = $remainingSecurityBalance;
        }

        if ($this->hasColumn('wsvmaster', 'wbalance')) {
            $updates['wbalance'] = $remainingSecurityBalance;
        }

        if ($this->hasColumn('wsvmaster', 'lastmove')) {
            $updates['lastmove'] = $movedAt;
        }

        if ($updates !== []) {
            $security->forceFill($updates)->save();
        }
    }

    private function insertLoanLedger(
        Wlnmaster $loan,
        float $appliedAmount,
        float $remainingLoanBalance,
        DateTimeInterface $movedAt,
        string $reference,
    ): void {
        if (! $this->hasTable('wlnled')) {
            throw $this->amountException('Loan payment is unavailable right now.');
        }

        $entry = [
            'acctno' => (string) $loan->acctno,
            'lnnumber' => (string) $loan->lnnumber,
        ];

        if ($this->hasColumn('wlnled', 'lntype')) {
            $entry['lntype'] = $loan->lntype;
        }

        if ($this->hasColumn('wlnled', 'date_in')) {
            $entry['date_in'] = $movedAt;
        }

        if ($this->hasColumn('wlnled', 'principal')) {
            $entry['principal'] = 0;
        }

        if ($this->hasColumn('wlnled', 'payments')) {
            $entry['payments'] = $appliedAmount;
        }

        if ($this->hasColumn('wlnled', 'balance')) {
            $entry['balance'] = $remainingLoanBalance;
        }

        if ($this->hasColumn('wlnled', 'debit')) {
            $entry['debit'] = 0;
        }

        if ($this->hasColumn('wlnled', 'credit')) {
            $entry['credit'] = 0;
        }

        if ($this->hasColumn('wlnled', 'accruedint')) {
            $entry['accruedint'] = 0;
        }

        if ($this->hasColumn('wlnled', 'grouploan')) {
            $entry['grouploan'] = 'Paid from loan security';
        }

        if ($this->hasColumn('wlnled', 'mreference')) {
            $entry['mreference'] = $reference;
        }

        if ($this->hasColumn('wlnled', 'controlno')) {
            $entry['controlno'] = $reference;
        }

        if ($this->hasColumn('wlnled', 'transno')) {
            $entry['transno'] = $reference;
        }

        Wlnled::query()->insert($entry);
    }

    private function insertSecurityLedger(
        Wsvmaster $security,
        float $appliedAmount,
        float $remainingSecurityBalance,
        DateTimeInterface $movedAt,
    ): void {
        if (! $this->hasTable('wsavled')) {
            throw $this->amountException('Loan payment is unavailable right now.');
        }

        $entry = [
            'acctno' => (string) $security->acctno,
            'svnumber' => (string) $security->svnumber,
        ];

        if ($this->hasColumn('wsavled', 'svtype')) {
            $entry['svtype'] = $security->svtype;
        }

        if ($this->hasColumn('wsavled', 'typecode')) {
            $entry['typecode'] = self::LOAN_SECURITY_TYPECODE;
        }

        if ($this->hasColumn('wsavled', 'date_in')) {
            $entry['date_in'] = $movedAt;
        }

        if ($this->hasColumn('wsavled', 'deposit')) {
            $entry['deposit'] = 0;
        }

        if ($this->hasColumn('wsavled', 'withdrawal')) {
            $entry['withdrawal'] = $appliedAmount;
        }

        if ($this->hasColumn('wsavled', 'balance')) {
            $entry['balance'] = $remainingSecurityBalance;
        }

        Wsavled::query()->insert($entry);
    }

    private function amountException(string $message): ValidationException
    {
        return ValidationException::withMessages([
            'amount' => $message,
        ]);
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', ',');
    }

    /**
     * @return array{
     *     svnumber: null,
     *     currentBalance: float,
     *     minimumBalance: float,
     *     maxPayable: float
     * }
     */
    private function defaultPaymentPanelData(): array
    {
        return [
            'svnumber' => null,
            'currentBalance' => 0.0,
            'minimumBalance' => self::MINIMUM_SECURITY_BALANCE,
            'maxPayable' => 0.0,
        ];
    }

    private function hasTable(string $table): bool
    {
        return $this->schemaCapabilities->hasTable($table);
    }

    private function hasColumn(string $table, string $column): bool
    {
        return $this->schemaCapabilities->hasColumn($table, $column);
    }
}

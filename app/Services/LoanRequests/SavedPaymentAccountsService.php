<?php

namespace App\Services\LoanRequests;

use App\LoanPaymentOption;
use App\LoanReleaseMethod;
use App\Models\LoanRequest;
use App\Models\MemberApplicationProfile;
use App\Models\MemberPaymentAccount;
use Illuminate\Support\Collection;

/**
 * A member's reusable bank/ATM account list, shared by both the loan
 * release-method and repayment-method pickers. Every lookup and mutation
 * here is scoped to the owning MemberApplicationProfile so one member can
 * never read, load, or delete another member's saved accounts.
 */
class SavedPaymentAccountsService
{
    /**
     * Projection for the picker -- id/label/last_used_at plus all
     * bank fields needed to render each accordion row.
     *
     * @return Collection<int, array{id: int, label: string, has_custom_label: bool, bank_name: ?string, account_name: ?string, account_number: ?string, account_type: ?string, atm_number: ?string, bank_branch: ?string, atm_holder_name: ?string, last_used_at: string|null}>
     */
    public function listFor(MemberApplicationProfile $profile): Collection
    {
        return MemberPaymentAccount::query()
            ->where('member_application_profile_id', $profile->id)
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (MemberPaymentAccount $account): array => $this->present($account));
    }

    /**
     * Shared projection used by the list, create, and update endpoints so
     * they all return the same shape -- notably `has_custom_label`, which
     * lets the frontend tell a member-typed label apart from the
     * bank/account-number fallback so it can pick the right number to mask
     * (bank account vs ATM) per method context.
     *
     * @return array{id: int, label: string, has_custom_label: bool, bank_name: ?string, account_name: ?string, account_number: ?string, account_type: ?string, atm_number: ?string, bank_branch: ?string, atm_holder_name: ?string, last_used_at: string|null}
     */
    public function present(MemberPaymentAccount $account): array
    {
        return [
            'id' => $account->id,
            'label' => $account->displayLabel(),
            'has_custom_label' => trim((string) $account->label) !== '',
            'bank_name' => $account->bank_name,
            'account_name' => $account->account_name,
            'account_number' => $account->account_number,
            'account_type' => $account->account_type,
            'atm_number' => $account->atm_number,
            'bank_branch' => $account->bank_branch,
            'atm_holder_name' => $account->atm_holder_name,
            'last_used_at' => $account->last_used_at?->toIso8601String(),
        ];
    }

    /**
     * Full record for a saved payment account, scoped to the owning profile.
     * Returns null (never another member's record) when the id doesn't
     * belong to this profile.
     */
    public function find(MemberApplicationProfile $profile, int $id): ?MemberPaymentAccount
    {
        return MemberPaymentAccount::query()
            ->where('member_application_profile_id', $profile->id)
            ->where('id', $id)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(MemberApplicationProfile $profile, array $data): MemberPaymentAccount
    {
        return $profile->paymentAccounts()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(MemberApplicationProfile $profile, int $id, array $data): MemberPaymentAccount
    {
        $account = $this->find($profile, $id);

        if ($account === null) {
            abort(404);
        }

        $account->update($data);

        return $account;
    }

    /**
     * Scoped delete -- silently no-ops when the id doesn't belong to this
     * profile, same as find().
     */
    public function destroy(MemberApplicationProfile $profile, int $id): void
    {
        $account = $this->find($profile, $id);

        if ($account === null) {
            return;
        }

        $clears = [];

        if ($profile->release_saved_account_id === $account->id) {
            $clears['release_saved_account_id'] = null;
        }

        if ($profile->payment_saved_account_id === $account->id) {
            $clears['payment_saved_account_id'] = null;
        }

        if ($clears !== []) {
            $profile->update($clears);
        }

        $account->delete();
    }

    public function touchLastUsed(MemberPaymentAccount $account): void
    {
        $account->update(['last_used_at' => now()]);
    }

    /**
     * Frozen copy of the account details behind a member's chosen release /
     * repayment accounts. Written into loan_requests.account_snapshot_json
     * the moment a loan is approved so the Authorization and Affidavit
     * documents keep printing the details that were in effect at approval
     * time, even if the member later edits or deletes the saved account.
     *
     * @param  array{release?:?int, payment?:?int}  $accountIds
     * @return array{release: array<string, mixed>|null, payment: array<string, mixed>|null}
     */
    public function resolveApprovalSnapshot(MemberApplicationProfile $profile, array $accountIds): array
    {
        return [
            'release' => $this->resolveAccountDetails($profile, $accountIds['release'] ?? null),
            'payment' => $this->resolveAccountDetails($profile, $accountIds['payment'] ?? null),
        ];
    }

    /**
     * Resolves and freezes the account details in effect for a loan request
     * at approval time. The EAV account ids the member actually picked for
     * this loan win over the profile defaults; the frozen snapshot is what
     * document generation reads afterwards.
     *
     * @param  array<string, mixed>  $flatValues
     * @return array{release: array<string, mixed>|null, payment: array<string, mixed>|null}|null
     */
    public function snapshotForApproval(LoanRequest $loanRequest, array $flatValues): ?array
    {
        $profile = $loanRequest->user?->memberApplicationProfile;

        if ($profile === null) {
            return null;
        }

        $releaseMethod = $flatValues['release_method'] ?? $profile->release_method;
        $needsReleaseAccount = in_array(
            $releaseMethod,
            [LoanReleaseMethod::Atm->value, LoanReleaseMethod::BankTransfer->value],
            true,
        );

        $paymentOption = $flatValues['payment_option'] ?? $profile->payment_option;
        $needsPaymentAccount = $paymentOption === LoanPaymentOption::AtmDeduction->value;

        return $this->resolveApprovalSnapshot($profile, [
            'release' => $needsReleaseAccount
                ? $this->accountIdOrNull($flatValues['release_saved_account_id'] ?? $profile->release_saved_account_id)
                : null,
            'payment' => $needsPaymentAccount
                ? $this->accountIdOrNull($flatValues['payment_saved_account_id'] ?? $profile->payment_saved_account_id)
                : null,
        ]);
    }

    private function accountIdOrNull(mixed $value): ?int
    {
        $value = $value === null || $value === '' ? null : (int) $value;

        return $value !== null && $value > 0 ? $value : null;
    }

    public function resolveAccountDetails(MemberApplicationProfile $profile, ?int $accountId): ?array
    {
        if ($accountId === null) {
            return null;
        }

        $account = $this->find($profile, $accountId);

        if ($account === null) {
            return null;
        }

        return [
            'bank_name' => $account->bank_name,
            'account_name' => $account->account_name,
            'account_number' => $account->account_number,
            'account_type' => $account->account_type,
            'atm_number' => $account->atm_number,
            'bank_branch' => $account->bank_branch,
            'atm_holder_name' => $account->atm_holder_name,
        ];
    }
}

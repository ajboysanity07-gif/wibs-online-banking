<?php

namespace App\Services\LoanRequests;

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
     * Lightweight projection for the Shopee-style picker -- id/label/last_used_at
     * plus the bank fields needed to render each row, never the full record.
     *
     * @return Collection<int, array{id: int, label: string, bank_name: ?string, account_number: ?string, last_used_at: string|null}>
     */
    public function listFor(MemberApplicationProfile $profile): Collection
    {
        return MemberPaymentAccount::query()
            ->where('member_application_profile_id', $profile->id)
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (MemberPaymentAccount $account): array => [
                'id' => $account->id,
                'label' => $account->displayLabel(),
                'bank_name' => $account->bank_name,
                'account_number' => $account->account_number,
                'last_used_at' => $account->last_used_at?->toIso8601String(),
            ]);
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
        $this->find($profile, $id)?->delete();
    }

    public function touchLastUsed(MemberPaymentAccount $account): void
    {
        $account->update(['last_used_at' => now()]);
    }
}

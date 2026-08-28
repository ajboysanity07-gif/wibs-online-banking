<?php

namespace App\Concerns;

use App\Services\LoanRequests\SavedPaymentAccountsService;
use Illuminate\Validation\Rule;

/**
 * Resolves a member-picked saved payment account (release_saved_account_id /
 * payment_saved_account_id) into the legacy payout_ and payment_ keys the
 * existing requiredIf() rules already check, before rules() runs -- mirrors
 * LoanRequestProcessingService::updatePaymentMethodByMember(). Shared by the
 * FormRequests that let a member set their release/repayment method: profile
 * settings (flat keys) and the loan request wizard (nested under "banking").
 */
trait ResolvesSavedPaymentAccountFields
{
    /**
     * @param  string|null  $sectionKey  When set, reads/writes under this
     *                                   nested array key (e.g. "banking")
     *                                   instead of the request's top level.
     */
    private function mergeSavedPaymentAccountFields(?string $sectionKey = null): void
    {
        $profile = $this->user()?->memberApplicationProfile;

        if ($profile === null) {
            return;
        }

        $prefix = $sectionKey !== null ? "{$sectionKey}." : '';
        $service = app(SavedPaymentAccountsService::class);
        $merge = [];

        $releaseAccountId = $this->input("{$prefix}release_saved_account_id");

        if ($releaseAccountId !== null) {
            $account = $service->find($profile, (int) $releaseAccountId);

            if ($account !== null) {
                $merge['payout_bank_name'] = $account->bank_name;
                $merge['payout_account_name'] = $account->account_name;
                $merge['payout_account_number'] = $account->account_number;
                $merge['payout_account_type'] = $account->account_type;
                $merge['payout_atm_number'] = $account->atm_number;
                $merge['payout_bank_branch'] = $account->bank_branch;
                $merge['payout_atm_holder_name'] = $account->atm_holder_name;
            }
        }

        $paymentAccountId = $this->input("{$prefix}payment_saved_account_id");

        if ($paymentAccountId !== null) {
            $account = $service->find($profile, (int) $paymentAccountId);

            if ($account !== null) {
                $merge['payment_bank_name'] = $account->bank_name;
                $merge['payment_account_name'] = $account->account_name;
                $merge['payment_account_number'] = $account->account_number;
                $merge['payment_account_type'] = $account->account_type;
                $merge['payment_atm_number'] = $account->atm_number;
                $merge['payment_bank_branch'] = $account->bank_branch;
                $merge['payment_atm_holder_name'] = $account->atm_holder_name;
            }
        }

        if ($merge === []) {
            return;
        }

        if ($sectionKey === null) {
            $this->merge($merge);

            return;
        }

        $this->merge([
            $sectionKey => array_merge($this->input($sectionKey, []), $merge),
        ]);
    }

    /**
     * @return array<string, array<mixed>>
     */
    private function savedPaymentAccountRules(?string $sectionKey, callable $releaseNeedsAccount, callable $paymentNeedsAccount): array
    {
        $prefix = $sectionKey !== null ? "{$sectionKey}." : '';
        $profileId = $this->user()?->memberApplicationProfile?->id;

        return [
            "{$prefix}release_saved_account_id" => [
                Rule::requiredIf($releaseNeedsAccount),
                'nullable',
                'integer',
                Rule::exists('member_payment_accounts', 'id')->where(
                    fn ($query) => $query->where('member_application_profile_id', $profileId),
                ),
            ],
            "{$prefix}payment_saved_account_id" => [
                Rule::requiredIf($paymentNeedsAccount),
                'nullable',
                'integer',
                Rule::exists('member_payment_accounts', 'id')->where(
                    fn ($query) => $query->where('member_application_profile_id', $profileId),
                ),
            ],
        ];
    }
}

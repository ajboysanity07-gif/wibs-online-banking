<?php

namespace App\Concerns;

use Illuminate\Validation\Rule;

/**
 * Shared validation for a member-picked saved payment account
 * (release_saved_account_id / payment_saved_account_id). The account
 * details themselves live only in member_payment_accounts -- the requests
 * that use this trait store an id, never the retired payout and payment
 * free-text snapshot keys.
 */
trait ResolvesSavedPaymentAccountFields
{
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

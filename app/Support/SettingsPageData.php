<?php

namespace App\Support;

use App\Models\MemberDependentProfile;
use App\Services\LoanRequests\DependentsProfileSyncService;
use App\Services\Locations\PsgcService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Features;

class SettingsPageData
{
    /**
     * @return array<string, mixed>
     */
    public static function fromRequest(Request $request, string $initialTab): array
    {
        $schema = app(SchemaCapabilities::class);
        $psgc = app(PsgcService::class);
        $user = $request->user();
        $user?->loadMissing(
            'adminProfile',
            'memberApplicationProfile',
        );
        $hasMemberAccess = $user?->hasMemberAccess() ?? false;

        $adminProfile = $user?->adminProfile;
        $memberApplicationProfile = $user?->memberApplicationProfile;
        $twoFactorAvailable = Features::canManageTwoFactorAuthentication();
        $twoFactorEnabled = $twoFactorAvailable
            && $user?->hasEnabledTwoFactorAuthentication();

        $memberRecord = null;

        if ($user !== null && $hasMemberAccess && $schema->hasTable('wmaster')) {
            $user->loadMissing('wmaster');

            if ($user->wmaster !== null) {
                $hasStructuredName = $user->wmaster->hasStructuredNameParts();
                $parts = $user->wmaster->resolvedAddressParts($psgc);
                $displayAddress = LocationComposer::compose(
                    $parts['address1'],
                    $parts['address2'],
                    $parts['address3'],
                    $parts['barangay'],
                );
                $displayAddress = $displayAddress !== ''
                    ? $displayAddress
                    : trim((string) $user->wmaster->address);
                $displayAddress = $displayAddress !== '' ? $displayAddress : null;
                $numberOfChildren = null;

                if (
                    $schema->hasColumn('wmaster', 'dependent')
                    && $user->wmaster->dependent !== null
                ) {
                    $numberOfChildren = (string) $user->wmaster->dependent;
                }

                $memberRecord = [
                    'bname' => $user->wmaster->bname,
                    'fname' => $user->wmaster->fname,
                    'lname' => $user->wmaster->lname,
                    'mname' => $user->wmaster->mname,
                    'birthplace' => $user->wmaster->birthplace,
                    'birthplace_city' => $parts['birthplace_city'],
                    'birthplace_province' => $parts['birthplace_province'],
                    'birthday' => $user->wmaster->birthday?->toDateString(),
                    'address' => $user->wmaster->address,
                    'address1' => $parts['address1'],
                    'barangay' => $parts['barangay'],
                    'barangay_raw' => $parts['barangay_raw'],
                    'address2' => $parts['address2'],
                    'address2_raw' => $parts['address2_raw'],
                    'address3' => $parts['address3'],
                    'address3_raw' => $parts['address3_raw'],
                    'zip_code' => $parts['zip_code'],
                    'display_address' => $displayAddress,
                    'civilstat' => $user->wmaster->civilstat,
                    'occupation' => $user->wmaster->occupation,
                    'spouse_name' => $user->wmaster->spouse,
                    'housing_status' => $user->wmaster->restype !== null
                        ? (string) $user->wmaster->restype
                        : null,
                    'number_of_children' => $numberOfChildren,
                    'hasStructuredName' => $hasStructuredName,
                ];
            }
        }

        $hasProfileValue = static function (mixed $value): bool {
            if ($value === null) {
                return false;
            }

            if (is_string($value)) {
                return trim($value) !== '';
            }

            return true;
        };

        $profileBirthplaceCity = $memberApplicationProfile?->birthplace_city;
        $profileBirthplaceProvince = $memberApplicationProfile?->birthplace_province;

        if (
            $memberApplicationProfile !== null
            && ! $hasProfileValue($profileBirthplaceCity)
            && ! $hasProfileValue($profileBirthplaceProvince)
            && $hasProfileValue($memberApplicationProfile->birthplace)
        ) {
            $parsed = LocationComposer::parseLegacyBirthplace(
                $memberApplicationProfile->birthplace,
            );
            $profileBirthplaceCity = $parsed['city'];
            $profileBirthplaceProvince = $parsed['province'];
        }

        $profileEmployerAddress1 = $memberApplicationProfile?->employer_business_address1;
        $profileEmployerAddress2 = $memberApplicationProfile?->employer_business_address2;
        $profileEmployerAddress3 = $memberApplicationProfile?->employer_business_address3;

        if (
            $memberApplicationProfile !== null
            && ! $hasProfileValue($profileEmployerAddress1)
            && ! $hasProfileValue($profileEmployerAddress2)
            && ! $hasProfileValue($profileEmployerAddress3)
            && $hasProfileValue($memberApplicationProfile->employer_business_address)
        ) {
            $parsed = LocationComposer::parseLegacyAddress(
                $memberApplicationProfile->employer_business_address,
            );
            $profileEmployerAddress1 = $parsed['address1'];
            $profileEmployerAddress2 = $parsed['address2'];
            $profileEmployerAddress3 = $parsed['address3'];
        }

        $profileHomeAddress1 = $memberApplicationProfile?->home_address1;
        $profileHomeAddress2 = $memberApplicationProfile?->home_address2;
        $profileHomeAddress3 = $memberApplicationProfile?->home_address3;

        if (
            $memberApplicationProfile !== null
            && ! $hasProfileValue($profileHomeAddress1)
            && ! $hasProfileValue($profileHomeAddress2)
            && ! $hasProfileValue($profileHomeAddress3)
            && $hasProfileValue($memberApplicationProfile->home_address)
        ) {
            $parsed = LocationComposer::parseLegacyAddress(
                $memberApplicationProfile->home_address,
            );
            $profileHomeAddress1 = $parsed['address1'];
            $profileHomeAddress2 = $parsed['address2'];
            $profileHomeAddress3 = $parsed['address3'];
        }

        $memberProfilePayload = $memberApplicationProfile
            ? [
                'nickname' => $memberApplicationProfile->nickname,
                'birthplace' => $memberApplicationProfile->birthplace,
                'birthplace_city' => $profileBirthplaceCity,
                'birthplace_province' => $profileBirthplaceProvince,
                'birthplace_barangay' => $memberApplicationProfile->birthplace_barangay,
                'educational_attainment' => $memberApplicationProfile->educational_attainment,
                'length_of_stay' => $memberApplicationProfile->length_of_stay,
                'home_address' => $memberApplicationProfile->home_address,
                'home_address1' => $profileHomeAddress1,
                'home_address_barangay' => $memberApplicationProfile->home_address_barangay,
                'home_address2' => $profileHomeAddress2,
                'home_address3' => $profileHomeAddress3,
                'home_address_zip' => $memberApplicationProfile->home_address_zip,
                'number_of_children' => $memberApplicationProfile->number_of_children,
                'civil_status' => $memberApplicationProfile->civil_status,
                'housing_status' => $memberApplicationProfile->housing_status,
                'spouse_name' => $memberApplicationProfile->spouse_name,
                'spouse_birthdate' => $memberApplicationProfile->spouse_birthdate?->toDateString(),
                'spouse_cell_no' => $memberApplicationProfile->spouse_cell_no,
                'employment_type' => $memberApplicationProfile->employment_type,
                'employer_business_name' => $memberApplicationProfile->employer_business_name,
                'employer_business_address' => $memberApplicationProfile->employer_business_address,
                'employer_business_address1' => $profileEmployerAddress1,
                'employer_business_address_barangay' => $memberApplicationProfile->employer_business_address_barangay,
                'employer_business_address2' => $profileEmployerAddress2,
                'employer_business_address3' => $profileEmployerAddress3,
                'employer_business_address_zip' => $memberApplicationProfile->employer_business_address_zip,
                'telephone_no' => $memberApplicationProfile->telephone_no,
                'current_position' => $memberApplicationProfile->current_position,
                'nature_of_business' => $memberApplicationProfile->nature_of_business,
                'years_in_work_business' => $memberApplicationProfile->years_in_work_business,
                'gross_monthly_income' => $memberApplicationProfile->gross_monthly_income !== null
                    ? (string) $memberApplicationProfile->gross_monthly_income
                    : null,
                'payday' => $memberApplicationProfile->payday,
                'payout_bank_name' => $memberApplicationProfile->payout_bank_name,
                'payout_account_name' => $memberApplicationProfile->payout_account_name,
                'payout_account_number' => $memberApplicationProfile->payout_account_number,
                'payout_account_type' => $memberApplicationProfile->payout_account_type,
                'release_method' => $memberApplicationProfile->release_method,
                'payout_atm_number' => $memberApplicationProfile->payout_atm_number,
                'payout_bank_branch' => $memberApplicationProfile->payout_bank_branch,
                'payout_atm_holder_name' => $memberApplicationProfile->payout_atm_holder_name,
                'payment_option' => $memberApplicationProfile->payment_option,
                'payment_bank_name' => $memberApplicationProfile->payment_bank_name,
                'payment_account_name' => $memberApplicationProfile->payment_account_name,
                'payment_account_number' => $memberApplicationProfile->payment_account_number,
                'payment_account_type' => $memberApplicationProfile->payment_account_type,
                'payment_atm_number' => $memberApplicationProfile->payment_atm_number,
                'payment_bank_branch' => $memberApplicationProfile->payment_bank_branch,
                'payment_atm_holder_name' => $memberApplicationProfile->payment_atm_holder_name,
                'source_of_fund_wealth' => $memberApplicationProfile->source_of_fund_wealth,
                'id_type' => $memberApplicationProfile->id_type,
                'id_type_other' => $memberApplicationProfile->id_type_other,
                'id_number' => $memberApplicationProfile->id_number,
                'height_cm' => $memberApplicationProfile->height_cm,
                'weight_kg' => $memberApplicationProfile->weight_kg,
                'profile_completed_at' => $memberApplicationProfile->profile_completed_at?->toDateTimeString(),
            ]
            : null;

        $profileMissingFields = [];
        $profileMissingFieldKeys = [];

        if ($user !== null && $hasMemberAccess) {
            $profileMissingFieldKeys = $user->missingMemberApplicationProfileFields(
                $memberApplicationProfile,
            );
            $profileMissingFields = $user->missingMemberApplicationProfileFieldLabels(
                $memberApplicationProfile,
            );

        }

        $profileCompletion = [
            'isComplete' => $user?->memberApplicationProfileIsComplete() ?? false,
            'completedAt' => $memberApplicationProfile?->profile_completed_at?->toDateTimeString(),
            'missingFields' => $profileMissingFields,
            'missingFieldKeys' => $profileMissingFieldKeys,
        ];

        return [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'adminProfile' => $adminProfile
                ? [
                    'fullname' => $adminProfile->fullname,
                    'profilePicUrl' => $adminProfile->profile_pic_path
                        ? Storage::disk('public')->url($adminProfile->profile_pic_path)
                        : null,
                ]
                : null,
            'loanManagerSignature' => null,
            'memberRecord' => $memberRecord,
            'memberApplicationProfile' => $memberProfilePayload,
            'dependents' => $memberApplicationProfile
                ? Arr::only(
                    app(DependentsProfileSyncService::class)->read($memberApplicationProfile),
                    MemberDependentProfile::fieldKeys(),
                )
                : null,
            'initialTab' => $initialTab,
            'profileCompletion' => $profileCompletion,
            'onboarding' => $request->boolean('onboarding'),
            'twoFactorAvailable' => $twoFactorAvailable,
            'twoFactorEnabled' => $twoFactorEnabled,
            'requiresConfirmation' => Features::optionEnabled(
                Features::twoFactorAuthentication(),
                'confirm',
            ),
        ];
    }
}

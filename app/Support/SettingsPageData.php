<?php

namespace App\Support;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
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
        $user = $request->user();
        $user?->loadMissing('adminProfile', 'memberApplicationProfile');
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
                $birthplaceParts = LocationComposer::parseLegacyBirthplace(
                    $user->wmaster->birthplace,
                );
                $address1 = trim((string) $user->wmaster->address2);
                $address2 = trim((string) $user->wmaster->address3);
                $address3 = trim((string) $user->wmaster->address4);
                $displayAddress = LocationComposer::compose(
                    $address1 !== '' ? $address1 : null,
                    $address2 !== '' ? $address2 : null,
                    $address3 !== '' ? $address3 : null,
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
                    'birthplace_city' => $birthplaceParts['city'],
                    'birthplace_province' => $birthplaceParts['province'],
                    'birthday' => $user->wmaster->birthday?->toDateString(),
                    'address' => $user->wmaster->address,
                    'address1' => $address1 !== '' ? $address1 : null,
                    'address2' => $address2 !== '' ? $address2 : null,
                    'address3' => $address3 !== '' ? $address3 : null,
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

        $memberProfilePayload = $memberApplicationProfile
            ? [
                'nickname' => $memberApplicationProfile->nickname,
                'birthplace' => $memberApplicationProfile->birthplace,
                'birthplace_city' => $profileBirthplaceCity,
                'birthplace_province' => $profileBirthplaceProvince,
                'educational_attainment' => $memberApplicationProfile->educational_attainment,
                'length_of_stay' => $memberApplicationProfile->length_of_stay,
                'number_of_children' => $memberApplicationProfile->number_of_children,
                'spouse_name' => $memberApplicationProfile->spouse_name,
                'spouse_age' => $memberApplicationProfile->spouse_age,
                'spouse_cell_no' => $memberApplicationProfile->spouse_cell_no,
                'employment_type' => $memberApplicationProfile->employment_type,
                'employer_business_name' => $memberApplicationProfile->employer_business_name,
                'employer_business_address' => $memberApplicationProfile->employer_business_address,
                'employer_business_address1' => $profileEmployerAddress1,
                'employer_business_address2' => $profileEmployerAddress2,
                'employer_business_address3' => $profileEmployerAddress3,
                'telephone_no' => $memberApplicationProfile->telephone_no,
                'current_position' => $memberApplicationProfile->current_position,
                'nature_of_business' => $memberApplicationProfile->nature_of_business,
                'years_in_work_business' => $memberApplicationProfile->years_in_work_business,
                'gross_monthly_income' => $memberApplicationProfile->gross_monthly_income !== null
                    ? (string) $memberApplicationProfile->gross_monthly_income
                    : null,
                'payday' => $memberApplicationProfile->payday,
                'profile_completed_at' => $memberApplicationProfile->profile_completed_at?->toDateTimeString(),
            ]
            : null;

        $profileMissingFields = [];
        $profileWarnings = [];

        if ($user !== null && $hasMemberAccess) {
            $profileMissingFields = $user->missingMemberApplicationProfileFieldLabels(
                $memberApplicationProfile,
            );

            if ($schema->hasTable('wmaster')) {
                $user->loadMissing('wmaster');

                if ($user->wmaster === null) {
                    $profileWarnings[] = 'Verified member record is missing.';
                } else {
                    $missingCanonicalFields = $user->wmaster->missingRequiredProfileFieldLabels();

                    if ($missingCanonicalFields !== []) {
                        $profileWarnings[] = sprintf(
                            'Verified member record is missing: %s.',
                            implode(', ', $missingCanonicalFields),
                        );
                    }
                }
            }
        }

        $profileCompletion = [
            'isComplete' => $user?->memberApplicationProfileIsComplete() ?? false,
            'completedAt' => $memberApplicationProfile?->profile_completed_at?->toDateTimeString(),
            'missingFields' => $profileMissingFields,
            'warnings' => $profileWarnings,
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
            'memberRecord' => $memberRecord,
            'memberApplicationProfile' => $memberProfilePayload,
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

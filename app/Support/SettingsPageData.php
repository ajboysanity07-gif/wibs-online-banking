<?php

namespace App\Support;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Features;

class SettingsPageData
{
    /**
     * @return array<string, mixed>
     */
    public static function fromRequest(Request $request, string $initialTab): array
    {
        $user = $request->user();
        $user?->loadMissing('adminProfile', 'memberApplicationProfile');

        $adminProfile = $user?->adminProfile;
        $memberApplicationProfile = $user?->memberApplicationProfile;
        $twoFactorAvailable = Features::canManageTwoFactorAuthentication();
        $twoFactorEnabled = $twoFactorAvailable
            && $user?->hasEnabledTwoFactorAuthentication();

        $memberRecord = null;

        if ($user !== null && $adminProfile === null && Schema::hasTable('wmaster')) {
            $user->loadMissing('wmaster');

            if ($user->wmaster !== null) {
                $hasStructuredName = $user->wmaster->hasStructuredNameParts();
                $addressParts = array_filter([
                    trim((string) $user->wmaster->address2),
                    trim((string) $user->wmaster->address3),
                    trim((string) $user->wmaster->address4),
                ], static fn (string $value): bool => $value !== '');
                $displayAddress = $addressParts !== []
                    ? implode(', ', $addressParts)
                    : trim((string) $user->wmaster->address);
                $displayAddress = $displayAddress !== '' ? $displayAddress : null;
                $numberOfChildren = null;

                if (
                    Schema::hasColumn('wmaster', 'dependent')
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
                    'birthday' => $user->wmaster->birthday?->toDateString(),
                    'address' => $user->wmaster->address,
                    'address2' => $user->wmaster->address2,
                    'address3' => $user->wmaster->address3,
                    'address4' => $user->wmaster->address4,
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

        $memberProfilePayload = $memberApplicationProfile
            ? [
                'nickname' => $memberApplicationProfile->nickname,
                'birthplace' => $memberApplicationProfile->birthplace,
                'educational_attainment' => $memberApplicationProfile->educational_attainment,
                'length_of_stay' => $memberApplicationProfile->length_of_stay,
                'number_of_children' => $memberApplicationProfile->number_of_children,
                'spouse_name' => $memberApplicationProfile->spouse_name,
                'spouse_age' => $memberApplicationProfile->spouse_age,
                'spouse_cell_no' => $memberApplicationProfile->spouse_cell_no,
                'employment_type' => $memberApplicationProfile->employment_type,
                'employer_business_name' => $memberApplicationProfile->employer_business_name,
                'employer_business_address' => $memberApplicationProfile->employer_business_address,
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

        $profileCompletion = [
            'isComplete' => $user?->memberApplicationProfileIsComplete() ?? false,
            'completedAt' => $memberApplicationProfile?->profile_completed_at?->toDateTimeString(),
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

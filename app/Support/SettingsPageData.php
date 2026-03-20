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
        $user = $request->user();
        $user?->loadMissing('adminProfile', 'memberApplicationProfile');

        $adminProfile = $user?->adminProfile;
        $memberApplicationProfile = $user?->memberApplicationProfile;
        $twoFactorAvailable = Features::canManageTwoFactorAuthentication();
        $twoFactorEnabled = $twoFactorAvailable
            && $user?->hasEnabledTwoFactorAuthentication();

        $memberProfilePayload = $memberApplicationProfile
            ? [
                'first_name' => $memberApplicationProfile->first_name,
                'last_name' => $memberApplicationProfile->last_name,
                'middle_name' => $memberApplicationProfile->middle_name,
                'nickname' => $memberApplicationProfile->nickname,
                'birthdate' => $memberApplicationProfile->birthdate?->toDateString(),
                'birthplace' => $memberApplicationProfile->birthplace,
                'age' => $memberApplicationProfile->age,
                'address' => $memberApplicationProfile->address,
                'length_of_stay' => $memberApplicationProfile->length_of_stay,
                'housing_status' => $memberApplicationProfile->housing_status,
                'civil_status' => $memberApplicationProfile->civil_status,
                'educational_attainment' => $memberApplicationProfile->educational_attainment,
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

        $profileCompletion = $memberApplicationProfile
            ? [
                'isComplete' => $memberApplicationProfile->isComplete(),
                'completedAt' => $memberApplicationProfile->profile_completed_at?->toDateTimeString(),
            ]
            : [
                'isComplete' => false,
                'completedAt' => null,
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

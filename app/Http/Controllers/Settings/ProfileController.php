<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\MemberApplicationProfile;
use App\Support\SettingsPageData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render(
            'settings/profile',
            SettingsPageData::fromRequest($request, 'profile'),
        );
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $user->fill(Arr::only($validated, [
            'username',
            'email',
            'phoneno',
        ]));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $user->loadMissing('adminProfile', 'memberApplicationProfile', 'userProfile');

        $adminProfile = $user->adminProfile;
        $hasMemberAccess = $user->hasMemberAccess();
        $profilePhotoPath = null;

        if ($request->hasFile('profile_photo')) {
            $profilePhotoPath = $request->file('profile_photo')
                ->store(
                    $adminProfile !== null
                        ? "profile-photos/admin/{$user->user_id}"
                        : "profile-photos/client/{$user->user_id}",
                    'public',
                );
        }

        if ($adminProfile !== null) {
            $adminProfileData = Arr::only($validated, ['fullname']);

            if ($profilePhotoPath !== null) {
                if ($adminProfile->profile_pic_path && $adminProfile->profile_pic_path !== $profilePhotoPath) {
                    Storage::disk('public')->delete($adminProfile->profile_pic_path);
                }

                $adminProfileData['profile_pic_path'] = $profilePhotoPath;
            }

            if ($adminProfileData !== []) {
                $adminProfile->update($adminProfileData);
            }
        }

        if ($hasMemberAccess) {
            $memberProfileData = Arr::only(
                $validated,
                MemberApplicationProfile::fields(),
            );

            $memberProfile = $user->memberApplicationProfile()->firstOrNew();
            $memberProfile->fill($memberProfileData);

            if ($profilePhotoPath !== null) {
                $userProfile = $user->userProfile()->firstOrNew([
                    'user_id' => $user->user_id,
                ]);

                if ($userProfile->profile_pic_path && $userProfile->profile_pic_path !== $profilePhotoPath) {
                    Storage::disk('public')->delete($userProfile->profile_pic_path);
                }

                $userProfile->profile_pic_path = $profilePhotoPath;
                $userProfile->save();
            }

            $user->syncMemberApplicationProfileCompletion($memberProfile);

            $user->setRelation('memberApplicationProfile', $memberProfile);

            if ($user->memberApplicationProfileIsComplete()) {
                return to_route('client.dashboard');
            }

            return to_route('profile.edit', ['onboarding' => 1]);
        }

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

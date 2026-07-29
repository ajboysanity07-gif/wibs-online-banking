<?php

namespace App\Services\LoanRequests;

use App\Models\MemberApplicationProfile;
use App\Models\MemberCoMaker;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * A member's reusable co-maker contact list -- distinct from LoanRequestPerson
 * (which stores the co-maker actually submitted on one specific loan). Every
 * lookup and mutation here is scoped to the owning MemberApplicationProfile so
 * one member can never read, load, or delete another member's saved contacts.
 */
class SavedCoMakersService
{
    /**
     * Lightweight projection for the wizard's picker -- id/label/last_used_at
     * only, never the full PII payload.
     *
     * @return Collection<int, array{id: int, label: string, last_used_at: string|null}>
     */
    public function listFor(MemberApplicationProfile $profile): Collection
    {
        return MemberCoMaker::query()
            ->where('member_application_profile_id', $profile->id)
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (MemberCoMaker $coMaker): array => [
                'id' => $coMaker->id,
                'label' => $coMaker->displayLabel(),
                'last_used_at' => $coMaker->last_used_at?->toIso8601String(),
            ]);
    }

    /**
     * Full record for a saved co-maker, scoped to the owning profile. Returns
     * null (never another member's record) when the id doesn't belong to
     * this profile.
     */
    public function find(MemberApplicationProfile $profile, int $id): ?MemberCoMaker
    {
        return MemberCoMaker::query()
            ->where('member_application_profile_id', $profile->id)
            ->where('id', $id)
            ->first();
    }

    /**
     * Persist a co-maker's details for reuse -- only called when the
     * borrower explicitly checked "save for reuse" on submission. Updates
     * the loaded contact in place when $existingId belongs to this profile,
     * otherwise creates a new one.
     *
     * @param  array<string, mixed>  $personPayload
     */
    public function saveOrUpdate(MemberApplicationProfile $profile, array $personPayload, ?int $existingId, ?string $label = null): MemberCoMaker
    {
        $data = [
            ...Arr::only($personPayload, MemberCoMaker::personFields()),
            'label' => $label,
            'last_used_at' => now(),
        ];

        $coMaker = $existingId !== null ? $this->find($profile, $existingId) : null;

        if ($coMaker !== null) {
            $coMaker->update($data);

            return $coMaker;
        }

        return $profile->coMakers()->create($data);
    }

    /**
     * Scoped delete -- silently no-ops when the id doesn't belong to this
     * profile, same as find().
     */
    public function destroy(MemberApplicationProfile $profile, int $id): void
    {
        $this->find($profile, $id)?->delete();
    }
}

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
     * Persist a co-maker's details for reuse -- called explicitly by the
     * member via the "Save co-maker" action. Updates the loaded contact in
     * place when $existingId belongs to this profile; otherwise looks for an
     * existing contact matching the same person (see findDuplicate()) and
     * updates that instead of forking a duplicate row. Only creates a new
     * record when neither match is found.
     *
     * @param  array<string, mixed>  $personPayload
     * @return array{coMaker: MemberCoMaker, wasDuplicate: bool}
     */
    public function saveOrUpdate(MemberApplicationProfile $profile, array $personPayload, ?int $existingId, ?string $label = null): array
    {
        $data = [
            ...Arr::only($personPayload, MemberCoMaker::personFields()),
            'label' => $label,
            'last_used_at' => now(),
        ];

        $coMaker = $existingId !== null ? $this->find($profile, $existingId) : null;
        $wasDuplicate = false;

        if ($coMaker === null) {
            $coMaker = $this->findDuplicate($profile, $data);
            $wasDuplicate = $coMaker !== null;
        }

        if ($coMaker !== null) {
            $coMaker->update($data);

            return ['coMaker' => $coMaker, 'wasDuplicate' => $wasDuplicate];
        }

        return ['coMaker' => $profile->coMakers()->create($data), 'wasDuplicate' => false];
    }

    /**
     * Finds an already-saved co-maker matching the same person -- normalized
     * first/last name plus at least one corroborating identity field
     * (birthdate or cell number), since there's no government-ID field to
     * key on. Used to avoid forking a duplicate record when the member
     * re-enters a contact's details from scratch instead of loading them
     * from the saved list.
     *
     * @param  array<string, mixed>  $data
     */
    public function findDuplicate(MemberApplicationProfile $profile, array $data, ?int $excludeId = null): ?MemberCoMaker
    {
        $firstName = $this->normalizeForMatch($data['first_name'] ?? null);
        $lastName = $this->normalizeForMatch($data['last_name'] ?? null);

        if ($firstName === '' || $lastName === '') {
            return null;
        }

        $birthdate = $this->normalizeForMatch($data['birthdate'] ?? null);
        $cellNo = $this->normalizeForMatch($data['cell_no'] ?? null);

        if ($birthdate === '' && $cellNo === '') {
            return null;
        }

        return MemberCoMaker::query()
            ->where('member_application_profile_id', $profile->id)
            ->when($excludeId !== null, fn ($query) => $query->whereKeyNot($excludeId))
            ->get()
            ->first(function (MemberCoMaker $candidate) use ($firstName, $lastName, $birthdate, $cellNo): bool {
                if ($this->normalizeForMatch($candidate->first_name) !== $firstName
                    || $this->normalizeForMatch($candidate->last_name) !== $lastName) {
                    return false;
                }

                $candidateBirthdate = $this->normalizeForMatch(
                    $candidate->birthdate?->toDateString(),
                );
                $candidateCellNo = $this->normalizeForMatch($candidate->cell_no);

                return ($birthdate !== '' && $birthdate === $candidateBirthdate)
                    || ($cellNo !== '' && $cellNo === $candidateCellNo);
            });
    }

    private function normalizeForMatch(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return strtolower((string) preg_replace('/[\s-]+/', ' ', trim((string) $value)));
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

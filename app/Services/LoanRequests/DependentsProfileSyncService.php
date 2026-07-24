<?php

namespace App\Services\LoanRequests;

use App\Models\MemberApplicationProfile;
use App\Models\MemberDependent;
use App\Models\MemberDependentProfile;

/**
 * Canonical read/write for a member's normalized dependent tables
 * (member_dependent_profiles/member_dependents), shared by the loan-request
 * wizard's submit-only sync and the Settings > Dependents tab's immediate
 * save.
 */
class DependentsProfileSyncService
{
    /**
     * Flatten a member's saved dependents into the fixed-slot field keys
     * (dependent_{category}_{slot}_{attribute}) used by both the wizard and
     * the Settings tab. Every possible slot is present in the result (null
     * when unset) so callers can bind form fields directly.
     *
     * @return array<string, string|null>
     */
    public function read(MemberApplicationProfile $profile): array
    {
        $profile->loadMissing('dependentProfile.dependents');
        $dependentProfile = $profile->dependentProfile;
        $dependents = $dependentProfile?->dependents;

        $values = [];

        foreach (MemberDependentProfile::CATEGORY_CAPS as $category => $cap) {
            for ($slot = 1; $slot <= $cap; $slot++) {
                foreach (['name', 'birthdate', 'cycle_status', 'cycle_number'] as $attribute) {
                    $values["dependent_{$category}_{$slot}_{$attribute}"] = null;
                }
            }
        }

        foreach ($dependents ?? [] as $dependent) {
            $prefix = "dependent_{$dependent->category}_{$dependent->slot}_";

            $values[$prefix.'name'] = $dependent->name;
            $values[$prefix.'birthdate'] = $dependent->birthdate?->toDateString();
            $values[$prefix.'cycle_status'] = $dependent->cycle_status;
            $values[$prefix.'cycle_number'] = $dependent->cycle_number;
        }

        $values['dependent_spouse_cycle_status'] = $dependentProfile?->spouse_cycle_status;
        $values['dependent_spouse_cycle_number'] = $dependentProfile?->spouse_cycle_number;

        return $values;
    }

    /**
     * True when the member has at least one saved dependent row.
     */
    public function hasSavedDependents(MemberApplicationProfile $profile): bool
    {
        $profile->loadMissing('dependentProfile.dependents');

        return (bool) $profile->dependentProfile?->dependents?->isNotEmpty();
    }

    /**
     * Write validated dependents data back onto the member's normalized
     * dependent tables, one row per non-empty category/slot.
     *
     * @param  array<string, mixed>  $dependentsPayload
     */
    public function sync(MemberApplicationProfile $profile, array $dependentsPayload): void
    {
        if ($dependentsPayload === []) {
            return;
        }

        $dependentProfile = MemberDependentProfile::query()->firstOrCreate([
            'member_application_profile_id' => $profile->id,
        ]);

        if (array_key_exists('dependent_spouse_cycle_status', $dependentsPayload) || array_key_exists('dependent_spouse_cycle_number', $dependentsPayload)) {
            $dependentProfile->update([
                'spouse_cycle_status' => $this->normalizeOptionalString($dependentsPayload['dependent_spouse_cycle_status'] ?? null),
                'spouse_cycle_number' => $this->normalizeOptionalInt($dependentsPayload['dependent_spouse_cycle_number'] ?? null),
            ]);
        }

        foreach (MemberDependentProfile::CATEGORY_CAPS as $category => $cap) {
            for ($slot = 1; $slot <= $cap; $slot++) {
                $prefix = "dependent_{$category}_{$slot}_";

                $existingRow = MemberDependent::query()
                    ->where('member_dependent_profile_id', $dependentProfile->id)
                    ->where('category', $category)
                    ->where('slot', $slot)
                    ->first();

                $name = $this->normalizeOptionalString($dependentsPayload[$prefix.'name'] ?? null);
                $birthdate = $this->normalizeOptionalString($dependentsPayload[$prefix.'birthdate'] ?? null);

                // Name/birthdate define whether the row exists at all --
                // clearing both removes the dependent, regardless of any
                // cycle data on file.
                if ($name === null && $birthdate === null) {
                    $existingRow?->delete();

                    continue;
                }

                // Cycle fields are submitted by the wizard but not by the
                // Settings > Dependents tab (see DependentCategorySection's
                // showCycleFields prop). When the caller's payload omits
                // them entirely, keep whatever the wizard already saved
                // instead of overwriting with null.
                $rowData = [
                    'name' => $name,
                    'birthdate' => $birthdate,
                    'cycle_status' => array_key_exists($prefix.'cycle_status', $dependentsPayload)
                        ? $this->normalizeOptionalString($dependentsPayload[$prefix.'cycle_status'])
                        : $existingRow?->cycle_status,
                    'cycle_number' => array_key_exists($prefix.'cycle_number', $dependentsPayload)
                        ? $this->normalizeOptionalInt($dependentsPayload[$prefix.'cycle_number'])
                        : $existingRow?->cycle_number,
                ];

                MemberDependent::query()->updateOrCreate(
                    [
                        'member_dependent_profile_id' => $dependentProfile->id,
                        'category' => $category,
                        'slot' => $slot,
                    ],
                    $rowData,
                );
            }
        }
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stringValue = trim((string) $value);

        return $stringValue !== '' ? $stringValue : null;
    }

    private function normalizeOptionalInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}

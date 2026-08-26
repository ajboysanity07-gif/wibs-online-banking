<?php

namespace App\Services\LoanRequests;

use App\Models\LoanRequest;
use App\Models\MemberDependent;
use App\Models\MemberDependentProfile;
use Illuminate\Validation\ValidationException;

/**
 * Resolves and confirms the Group Life Insurance (Generali) cycle status
 * (New/Old + cycle number) for the applicant, spouse, and each dependent
 * slot of a loan request's owning member.
 *
 * The member-self-reported cycle_status/cycle_number columns on
 * MemberDependentProfile/MemberDependent are NOT the source of truth here --
 * this service reads/writes the separate *_confirmed_cycle_* columns, which
 * only a loan processor can set (via a processed loan's processing-details
 * save). Once a person's slot has a confirmed record, it is locked: the
 * displayed value is always "Old" + confirmed_number + 1, and the processor
 * can no longer edit it -- the value simply advances on every subsequent
 * processed loan.
 */
class LoanRequestCycleStateService
{
    public function __construct(
        private LoanRequestDataService $dataService,
    ) {}

    /**
     * @return array<string, array{locked: bool, cycle_status: string, cycle_number: int}>
     */
    public function resolveState(LoanRequest $loanRequest): array
    {
        $dependentProfile = $this->dependentProfile($loanRequest);

        $state = [
            'applicant' => $this->resolveSlotState(
                $dependentProfile?->applicant_confirmed_cycle_status,
                $dependentProfile?->applicant_confirmed_cycle_number,
                $dependentProfile?->applicant_confirmed_by_loan_request_id,
                $loanRequest,
            ),
            'spouse' => $this->resolveSlotState(
                $dependentProfile?->spouse_confirmed_cycle_status,
                $dependentProfile?->spouse_confirmed_cycle_number,
                $dependentProfile?->spouse_confirmed_by_loan_request_id,
                $loanRequest,
            ),
        ];

        $dependentsByKey = [];

        foreach ($dependentProfile?->dependents ?? [] as $dependent) {
            $dependentsByKey["{$dependent->category}_{$dependent->slot}"] = $dependent;
        }

        foreach (MemberDependentProfile::CATEGORY_CAPS as $category => $cap) {
            for ($slot = 1; $slot <= $cap; $slot++) {
                $dependent = $dependentsByKey["{$category}_{$slot}"] ?? null;

                $state["{$category}_{$slot}"] = $this->resolveSlotState(
                    $dependent?->confirmed_cycle_status,
                    $dependent?->confirmed_cycle_number,
                    $dependent?->confirmed_by_loan_request_id,
                    $loanRequest,
                );
            }
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $processingPayload
     */
    public function assertNoLockedSlotOverridden(LoanRequest $loanRequest, array $processingPayload): void
    {
        $state = $this->resolveState($loanRequest);
        $errors = [];

        foreach ($this->slotFieldKeys() as $slotKey => $fieldKeys) {
            $slotState = $state[$slotKey] ?? null;

            if ($slotState === null || ! $slotState['locked']) {
                continue;
            }

            [$statusKey, $numberKey] = $fieldKeys;

            if (
                array_key_exists($statusKey, $processingPayload)
                && $processingPayload[$statusKey] !== null
                && $processingPayload[$statusKey] !== $slotState['cycle_status']
            ) {
                $errors["processing.{$statusKey}"] = 'This cycle status is locked from a prior processed loan and cannot be changed.';
            }

            if (
                array_key_exists($numberKey, $processingPayload)
                && $processingPayload[$numberKey] !== null
                && (int) $processingPayload[$numberKey] !== $slotState['cycle_number']
            ) {
                $errors["processing.{$numberKey}"] = 'This cycle number is locked from a prior processed loan and cannot be changed.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Called after the processing-details EAV values have been saved. For
     * every slot not yet locked, writes the just-saved value as the new
     * confirmed record for next time. Locked slots are left untouched.
     */
    public function confirm(LoanRequest $loanRequest): void
    {
        $state = $this->resolveState($loanRequest);
        $flatValues = $this->dataService->loadFlatValues($loanRequest);

        $dependentProfileUpdates = [];

        if (! ($state['applicant']['locked'] ?? false)) {
            $status = $flatValues['applicant_cycle_status'] ?? null;
            $number = $flatValues['applicant_cycle_number'] ?? null;

            if ($status !== null) {
                $dependentProfileUpdates['applicant_confirmed_cycle_status'] = $status;
                $dependentProfileUpdates['applicant_confirmed_cycle_number'] = $number !== null ? (int) $number : null;
                $dependentProfileUpdates['applicant_confirmed_by_loan_request_id'] = $loanRequest->id;
            }
        }

        if (! ($state['spouse']['locked'] ?? false)) {
            $status = $flatValues['dependent_spouse_cycle_status'] ?? null;
            $number = $flatValues['dependent_spouse_cycle_number'] ?? null;

            if ($status !== null) {
                $dependentProfileUpdates['spouse_confirmed_cycle_status'] = $status;
                $dependentProfileUpdates['spouse_confirmed_cycle_number'] = $number !== null ? (int) $number : null;
                $dependentProfileUpdates['spouse_confirmed_by_loan_request_id'] = $loanRequest->id;
            }
        }

        $dependentUpdates = [];

        foreach (MemberDependentProfile::CATEGORY_CAPS as $category => $cap) {
            for ($slot = 1; $slot <= $cap; $slot++) {
                $slotKey = "{$category}_{$slot}";

                if ($state[$slotKey]['locked'] ?? false) {
                    continue;
                }

                $status = $flatValues["dependent_{$category}_{$slot}_cycle_status"] ?? null;
                $number = $flatValues["dependent_{$category}_{$slot}_cycle_number"] ?? null;

                if ($status === null) {
                    continue;
                }

                $dependentUpdates[$slotKey] = [
                    'category' => $category,
                    'slot' => $slot,
                    'confirmed_cycle_status' => $status,
                    'confirmed_cycle_number' => $number !== null ? (int) $number : null,
                    'confirmed_by_loan_request_id' => $loanRequest->id,
                ];
            }
        }

        if ($dependentProfileUpdates === [] && $dependentUpdates === []) {
            return;
        }

        $memberApplicationProfile = $loanRequest->user?->memberApplicationProfile;

        if ($memberApplicationProfile === null) {
            return;
        }

        $dependentProfile = MemberDependentProfile::query()->firstOrCreate([
            'member_application_profile_id' => $memberApplicationProfile->id,
        ]);

        if ($dependentProfileUpdates !== []) {
            $dependentProfile->update($dependentProfileUpdates);
        }

        foreach ($dependentUpdates as $update) {
            MemberDependent::query()->updateOrCreate(
                [
                    'member_dependent_profile_id' => $dependentProfile->id,
                    'category' => $update['category'],
                    'slot' => $update['slot'],
                ],
                [
                    'confirmed_cycle_status' => $update['confirmed_cycle_status'],
                    'confirmed_cycle_number' => $update['confirmed_cycle_number'],
                    'confirmed_by_loan_request_id' => $update['confirmed_by_loan_request_id'],
                ],
            );
        }
    }

    private function dependentProfile(LoanRequest $loanRequest): ?MemberDependentProfile
    {
        $memberApplicationProfile = $loanRequest->user?->memberApplicationProfile;

        if ($memberApplicationProfile === null) {
            return null;
        }

        $memberApplicationProfile->loadMissing('dependentProfile.dependents');

        return $memberApplicationProfile->dependentProfile;
    }

    /**
     * A slot is locked only when a confirmed record exists AND it was
     * confirmed by a *different* loan request than the one currently being
     * viewed/saved. The confirming loan request remains free to edit its
     * own confirmation (e.g. fixing a typo before moving on), and sees the
     * actual saved value/number rather than a computed default.
     *
     * The Generali form labels cycle numbers as:
     *   New (1st-2nd)  —  cycles 1 and 2
     *   Old (3rd cycle & up ___)  —  cycles 3+
     *
     * So a confirmed New/1 advances to New/2, New/2 advances to Old/3,
     * and Old/N advances to Old/N+1.
     *
     * @return array{locked: bool, cycle_status: string, cycle_number: int}
     */
    private function resolveSlotState(
        ?string $confirmedStatus,
        ?int $confirmedNumber,
        ?int $confirmedByLoanRequestId,
        LoanRequest $loanRequest,
    ): array {
        if ($confirmedStatus !== null && $confirmedByLoanRequestId !== $loanRequest->id) {
            $nextNumber = ($confirmedNumber ?? 0) + 1;

            // New covers cycles 1-2; Old covers 3+.
            $nextStatus = ($confirmedStatus === 'New' && $nextNumber <= 2)
                ? 'New'
                : 'Old';

            return [
                'locked' => true,
                'cycle_status' => $nextStatus,
                'cycle_number' => $nextNumber,
            ];
        }

        if ($confirmedStatus !== null) {
            return [
                'locked' => false,
                'cycle_status' => $confirmedStatus,
                'cycle_number' => $confirmedNumber,
            ];
        }

        // No confirmed record yet: default to New/1 (first enrollment cycle).
        return [
            'locked' => false,
            'cycle_status' => 'New',
            'cycle_number' => 1,
        ];
    }

    /**
     * Maps every slot key to its [status_field_key, number_field_key] pair
     * within a processing-details `processing` payload.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private function slotFieldKeys(): array
    {
        $map = [
            'applicant' => ['applicant_cycle_status', 'applicant_cycle_number'],
            'spouse' => ['dependent_spouse_cycle_status', 'dependent_spouse_cycle_number'],
        ];

        foreach (MemberDependentProfile::CATEGORY_CAPS as $category => $cap) {
            for ($slot = 1; $slot <= $cap; $slot++) {
                $slotKey = "{$category}_{$slot}";
                $map[$slotKey] = [
                    "dependent_{$category}_{$slot}_cycle_status",
                    "dependent_{$category}_{$slot}_cycle_number",
                ];
            }
        }

        return $map;
    }
}

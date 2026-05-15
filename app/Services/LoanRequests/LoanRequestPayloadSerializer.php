<?php

namespace App\Services\LoanRequests;

use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\Models\LoanRequest;
use App\Models\LoanRequestCorrectionReport;
use App\Models\LoanRequestPerson;
use App\Support\LocationComposer;
use DateTimeInterface;

class LoanRequestPayloadSerializer
{
    private const CIVIL_STATUS_OPTIONS = [
        'Single',
        'Married',
        'Separated',
        'Widowed',
    ];

    private const PAYDAY_OPTIONS = [
        'Weekly',
        '15th',
        '30th',
        '15th & 30th',
        'Bi-Weekly',
        'Monthly',
    ];

    public function __construct(
        private LoanRequestDecisionService $decisionService,
    ) {}

    /**
     * @return array{
     *     loanRequest: array<string, mixed>,
     *     applicant: array<string, mixed>,
     *     coMakerOne: array<string, mixed>,
     *     coMakerTwo: array<string, mixed>
     * }
     */
    public function serializeDetail(LoanRequest $loanRequest): array
    {
        $loanRequest->loadMissing('people', 'reviewedBy', 'cancelledBy');

        return [
            'loanRequest' => $this->serializeLoanRequest($loanRequest),
            'applicant' => $this->serializePerson(
                $loanRequest,
                LoanRequestPersonRole::Applicant,
            ),
            'coMakerOne' => $this->serializePerson(
                $loanRequest,
                LoanRequestPersonRole::CoMakerOne,
            ),
            'coMakerTwo' => $this->serializePerson(
                $loanRequest,
                LoanRequestPersonRole::CoMakerTwo,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeLoanRequest(LoanRequest $loanRequest): array
    {
        $loanRequest->loadMissing(
            'reviewedBy',
            'cancelledBy',
            'correctedFrom',
            'correctedRequests',
        );
        $correctedRequest = $this->resolveCorrectedRequest($loanRequest);
        $correctionSaved = $loanRequest->corrected_from_id !== null
            ? $this->decisionService->hasSavedCorrectionAfterCreation(
                $loanRequest,
            )
            : false;
        $requiresCorrectionBeforeApproval = $this->decisionService
            ->requiresSavedCorrectionBeforeApproval($loanRequest);

        return [
            'id' => $loanRequest->id,
            'reference' => $loanRequest->reference,
            'status' => $this->normalizeStatus($loanRequest),
            'typecode' => $loanRequest->typecode,
            'loan_type_label_snapshot' => $loanRequest->loan_type_label_snapshot,
            'requested_amount' => $loanRequest->requested_amount,
            'requested_term' => $loanRequest->requested_term,
            'loan_purpose' => $loanRequest->loan_purpose,
            'availment_status' => $loanRequest->availment_status,
            'submitted_at' => $loanRequest->submitted_at?->toDateTimeString(),
            'reviewed_by' => $loanRequest->reviewedBy
                ? [
                    'user_id' => $loanRequest->reviewedBy->user_id,
                    'name' => $loanRequest->reviewedBy->name,
                ]
                : null,
            'reviewed_at' => $loanRequest->reviewed_at?->toDateTimeString(),
            'approved_amount' => $loanRequest->approved_amount,
            'approved_term' => $loanRequest->approved_term,
            'decision_notes' => $loanRequest->decision_notes,
            'cancelled_by' => $loanRequest->cancelledBy
                ? [
                    'user_id' => $loanRequest->cancelledBy->user_id,
                    'name' => $loanRequest->cancelledBy->name,
                ]
                : null,
            'cancelled_at' => $loanRequest->cancelled_at?->toDateTimeString(),
            'cancellation_reason' => $loanRequest->cancellation_reason,
            'corrected_from_id' => $loanRequest->corrected_from_id,
            'corrected_from_reference' => $loanRequest->correctedFrom?->reference,
            'corrected_request_id' => $correctedRequest?->id,
            'corrected_request_reference' => $correctedRequest?->reference,
            'corrected_request_status' => $correctedRequest !== null
                ? $this->normalizeStatus($correctedRequest)
                : null,
            'correction_saved' => $correctionSaved,
            'requires_correction_before_approval' => $requiresCorrectionBeforeApproval,
            'acctno' => $loanRequest->acctno,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function serializeCorrectionReports(LoanRequest $loanRequest): array
    {
        $reports = $loanRequest->correctionReports()
            ->with(['user', 'resolvedBy', 'dismissedBy'])
            ->orderByDesc('id')
            ->get();

        return $reports
            ->map(
                fn (LoanRequestCorrectionReport $report): array => $this
                    ->serializeCorrectionReport($report),
            )
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeCorrectionReport(
        LoanRequestCorrectionReport $report,
    ): array {
        $report->loadMissing('user', 'resolvedBy', 'dismissedBy');

        return [
            'id' => $report->id,
            'loan_request_id' => $report->loan_request_id,
            'status' => $report->status,
            'issue_description' => $report->issue_description,
            'correct_information' => $report->correct_information,
            'supporting_note' => $report->supporting_note,
            'admin_notes' => $report->admin_notes,
            'reported_at' => $report->created_at?->toDateTimeString(),
            'reported_by' => $report->user
                ? [
                    'user_id' => $report->user->user_id,
                    'name' => $report->user->name,
                    'acctno' => $report->user->acctno,
                ]
                : null,
            'resolved_by' => $report->resolvedBy
                ? [
                    'user_id' => $report->resolvedBy->user_id,
                    'name' => $report->resolvedBy->name,
                ]
                : null,
            'resolved_at' => $report->resolved_at?->toDateTimeString(),
            'dismissed_by' => $report->dismissedBy
                ? [
                    'user_id' => $report->dismissedBy->user_id,
                    'name' => $report->dismissedBy->name,
                ]
                : null,
            'dismissed_at' => $report->dismissed_at?->toDateTimeString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializePerson(
        LoanRequest $loanRequest,
        LoanRequestPersonRole $role,
    ): array {
        $loanRequest->loadMissing('people');

        $person = $loanRequest->people
            ->first(function (LoanRequestPerson $item) use ($role): bool {
                $itemRole = $item->role instanceof LoanRequestPersonRole
                    ? $item->role->value
                    : (string) $item->role;

                return $itemRole === $role->value;
            });

        if ($person === null) {
            return [];
        }

        return $this->hydrateStructuredPersonFields($person->toArray());
    }

    private function normalizeStatus(LoanRequest $loanRequest): string
    {
        $status = $loanRequest->status instanceof LoanRequestStatus
            ? $loanRequest->status->value
            : (string) $loanRequest->status;

        if ($status === LoanRequestStatus::Submitted->value) {
            return LoanRequestStatus::UnderReview->value;
        }

        return $status;
    }

    private function resolveCorrectedRequest(
        LoanRequest $loanRequest,
    ): ?LoanRequest {
        if (! $loanRequest->relationLoaded('correctedRequests')) {
            return null;
        }

        /** @var LoanRequest|null $correctedRequest */
        $correctedRequest = $loanRequest->correctedRequests
            ->sortByDesc('id')
            ->first();

        return $correctedRequest;
    }

    /**
     * @param  array<string, mixed>  $person
     * @return array<string, mixed>
     */
    private function hydrateStructuredPersonFields(array $person): array
    {
        $birthdate = $this->normalizeDateForInput($person['birthdate'] ?? null);
        $housingStatus = $this->normalizeHousingStatusValue(
            $person['housing_status'] ?? null,
        );
        $civilStatus = $this->normalizeCivilStatusValue(
            $person['civil_status'] ?? null,
        );
        $payday = $this->normalizePaydayValue($person['payday'] ?? null);

        $birthplaceCity = $this->normalizeOptionalString(
            $person['birthplace_city'] ?? null,
        );
        $birthplaceProvince = $this->normalizeOptionalString(
            $person['birthplace_province'] ?? null,
        );
        $legacyBirthplace = $this->normalizeOptionalString(
            $person['birthplace'] ?? null,
        );

        if ($birthplaceCity === null && $birthplaceProvince === null && $legacyBirthplace !== null) {
            $parsed = LocationComposer::parseLegacyBirthplace($legacyBirthplace);
            $birthplaceCity = $parsed['city'];
            $birthplaceProvince = $parsed['province'];
        }

        $birthplace = LocationComposer::composeBirthplace(
            $birthplaceCity,
            $birthplaceProvince,
        );
        $birthplace = $birthplace !== '' ? $birthplace : $legacyBirthplace;

        $address1 = $this->normalizeOptionalString($person['address1'] ?? null);
        $address2 = $this->normalizeOptionalString($person['address2'] ?? null);
        $address3 = $this->normalizeOptionalString($person['address3'] ?? null);
        $legacyAddress = $this->normalizeOptionalString($person['address'] ?? null);

        if ($address1 === null && $address2 === null && $address3 === null && $legacyAddress !== null) {
            $parsed = LocationComposer::parseLegacyAddress($legacyAddress);
            $address1 = $parsed['address1'];
            $address2 = $parsed['address2'];
            $address3 = $parsed['address3'];
        }

        $address = LocationComposer::compose($address1, $address2, $address3);
        $address = $address !== '' ? $address : $legacyAddress;

        $employerAddress1 = $this->normalizeOptionalString(
            $person['employer_business_address1'] ?? null,
        );
        $employerAddress2 = $this->normalizeOptionalString(
            $person['employer_business_address2'] ?? null,
        );
        $employerAddress3 = $this->normalizeOptionalString(
            $person['employer_business_address3'] ?? null,
        );
        $legacyEmployerAddress = $this->normalizeOptionalString(
            $person['employer_business_address'] ?? null,
        );

        if (
            $employerAddress1 === null
            && $employerAddress2 === null
            && $employerAddress3 === null
            && $legacyEmployerAddress !== null
        ) {
            $parsed = LocationComposer::parseLegacyAddress(
                $legacyEmployerAddress,
            );
            $employerAddress1 = $parsed['address1'];
            $employerAddress2 = $parsed['address2'];
            $employerAddress3 = $parsed['address3'];
        }

        $employerBusinessAddress = LocationComposer::compose(
            $employerAddress1,
            $employerAddress2,
            $employerAddress3,
        );
        $employerBusinessAddress = $employerBusinessAddress !== ''
            ? $employerBusinessAddress
            : $legacyEmployerAddress;

        return array_merge($person, [
            'birthdate' => $birthdate,
            'birthplace' => $birthplace,
            'birthplace_city' => $birthplaceCity,
            'birthplace_province' => $birthplaceProvince,
            'address' => $address,
            'address1' => $address1,
            'address2' => $address2,
            'address3' => $address3,
            'employer_business_address' => $employerBusinessAddress,
            'employer_business_address1' => $employerAddress1,
            'employer_business_address2' => $employerAddress2,
            'employer_business_address3' => $employerAddress3,
            'housing_status' => $housingStatus,
            'civil_status' => $civilStatus,
            'payday' => $payday,
        ]);
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function normalizeDateForInput(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        $candidate = substr($trimmed, 0, 10);

        return preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $candidate) === 1
            ? $candidate
            : null;
    }

    private function normalizeHousingStatusValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        $upper = strtoupper($trimmed);

        if (in_array($upper, ['OWNED', 'OWN', 'OWNER'], true)) {
            return 'OWNED';
        }

        if (in_array($upper, ['RENT', 'RENTAL', 'RENTED', 'RENTING'], true)) {
            return 'RENT';
        }

        return null;
    }

    private function normalizeCivilStatusValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        $upper = strtoupper($trimmed);

        $resolved = match ($upper) {
            'SINGLE' => 'Single',
            'MARRIED' => 'Married',
            'SEPARATED' => 'Separated',
            'WIDOWED' => 'Widowed',
            'ANNULLED' => null,
            default => $trimmed,
        };

        if ($resolved === null) {
            return null;
        }

        return in_array($resolved, self::CIVIL_STATUS_OPTIONS, true)
            ? $resolved
            : null;
    }

    private function normalizePaydayValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        if (in_array($trimmed, self::PAYDAY_OPTIONS, true)) {
            return $trimmed;
        }

        $upper = strtoupper($trimmed);
        $compact = preg_replace('/[^0-9A-Z]/', '', $upper) ?? '';

        if ($upper === 'WEEKLY') {
            return 'Weekly';
        }

        if ($upper === 'MONTHLY') {
            return 'Monthly';
        }

        if ($compact === 'BIWEEKLY') {
            return 'Bi-Weekly';
        }

        if ($compact === '15') {
            return '15th';
        }

        if ($compact === '30') {
            return '30th';
        }

        if (str_contains($upper, '15') && str_contains($upper, '30')) {
            return '15th & 30th';
        }

        return null;
    }
}

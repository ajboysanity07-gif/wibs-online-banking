<?php

namespace App\Services\LoanRequests;

use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\Models\LoanRequest;
use App\Models\LoanRequestPerson;
use App\Support\LocationComposer;

class LoanRequestPayloadSerializer
{
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
        $loanRequest->loadMissing('people', 'reviewedBy');

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
        $loanRequest->loadMissing('reviewedBy');

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
            'acctno' => $loanRequest->acctno,
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

    /**
     * @param  array<string, mixed>  $person
     * @return array<string, mixed>
     */
    private function hydrateStructuredPersonFields(array $person): array
    {
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
}

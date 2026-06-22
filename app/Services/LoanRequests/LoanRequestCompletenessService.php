<?php

namespace App\Services\LoanRequests;

use App\LoanRequestDocumentKey;
use App\LoanRequestDocumentReadinessStatus;
use App\LoanRequestPersonRole;
use App\Models\LoanRequest;
use App\Models\LoanRequestDocument;
use App\Models\LoanRequestPerson;

class LoanRequestCompletenessService
{
    private const REQUIRED_PERSONAL_FIELDS = [
        'first_name',
        'last_name',
        'cell_no',
        'civil_status',
        'birthdate',
    ];

    private const REQUIRED_ADDRESS_FIELDS = [
        'address1',
        'address2',
        'address3',
        'address',
    ];

    private const REQUIRED_EMPLOYMENT_FIELDS = [
        'employment_type',
        'employer_business_name',
        'gross_monthly_income',
    ];

    /**
     * @return array{
     *     percentage: int,
     *     completed: list<string>,
     *     missing: list<string>,
     *     missing_documents: list<string>
     * }
     */
    public function computeFor(LoanRequest $loanRequest): array
    {
        $loanRequest->loadMissing('people', 'documents');

        $applicant = $loanRequest->people
            ->first(fn (LoanRequestPerson $p): bool => $this->roleValue($p) === LoanRequestPersonRole::Applicant->value);

        $completed = [];
        $missing = [];

        $this->checkSection(
            'Personal info',
            $this->isPersonalInfoComplete($applicant),
            $completed,
            $missing,
        );

        $this->checkSection(
            'Employment',
            $this->isEmploymentComplete($applicant),
            $completed,
            $missing,
        );

        $this->checkSection(
            'Loan details',
            $this->isLoanDetailsComplete($loanRequest),
            $completed,
            $missing,
        );

        foreach (LoanRequestDocumentKey::cases() as $key) {
            $this->checkSection(
                $key->label(),
                $this->isDocumentComplete($loanRequest, $key),
                $completed,
                $missing,
            );
        }

        $total = count($completed) + count($missing);
        $percentage = $total > 0 ? (int) round((count($completed) / $total) * 100) : 0;

        $missingDocuments = array_values(
            array_map(
                static fn (LoanRequestDocumentKey $k): string => $k->value,
                array_filter(
                    LoanRequestDocumentKey::cases(),
                    fn (LoanRequestDocumentKey $k): bool => ! $this->isDocumentComplete($loanRequest, $k),
                ),
            ),
        );

        return [
            'percentage' => $percentage,
            'completed' => $completed,
            'missing' => $missing,
            'missing_documents' => $missingDocuments,
        ];
    }

    private function checkSection(string $label, bool $complete, array &$completed, array &$missing): void
    {
        if ($complete) {
            $completed[] = $label;
        } else {
            $missing[] = $label;
        }
    }

    private function isPersonalInfoComplete(?LoanRequestPerson $person): bool
    {
        if ($person === null) {
            return false;
        }

        foreach (self::REQUIRED_PERSONAL_FIELDS as $field) {
            if ($this->isBlank($person->$field)) {
                return false;
            }
        }

        $hasAddress = false;

        foreach (self::REQUIRED_ADDRESS_FIELDS as $field) {
            if (! $this->isBlank($person->$field)) {
                $hasAddress = true;
                break;
            }
        }

        return $hasAddress;
    }

    private function isEmploymentComplete(?LoanRequestPerson $person): bool
    {
        if ($person === null) {
            return false;
        }

        foreach (self::REQUIRED_EMPLOYMENT_FIELDS as $field) {
            if ($this->isBlank($person->$field)) {
                return false;
            }
        }

        return true;
    }

    private function isLoanDetailsComplete(LoanRequest $loanRequest): bool
    {
        return ! $this->isBlank($loanRequest->requested_amount)
            && ! $this->isBlank($loanRequest->requested_term)
            && ! $this->isBlank($loanRequest->loan_purpose);
    }

    private function isDocumentComplete(LoanRequest $loanRequest, LoanRequestDocumentKey $key): bool
    {
        /** @var LoanRequestDocument|null $document */
        $document = $loanRequest->documents
            ->first(fn (LoanRequestDocument $d): bool => $d->document_key === $key->value);

        if ($document === null) {
            return false;
        }

        if (! $document->is_applicable) {
            return true;
        }

        return $document->readiness_status === LoanRequestDocumentReadinessStatus::GeneratedCurrent;
    }

    private function roleValue(LoanRequestPerson $person): string
    {
        return $person->role instanceof LoanRequestPersonRole
            ? $person->role->value
            : (string) $person->role;
    }

    private function isBlank(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        return trim((string) $value) === '';
    }
}

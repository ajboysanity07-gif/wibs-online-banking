<?php

namespace App\Services\LoanRequests;

/**
 * Pure employer-category detection, extracted from
 * LoanRequestDocumentCatalog::authorityToDeductCategory()/barangayApplicable()
 * so it can be reused outside a persisted LoanRequest's applicant relation --
 * e.g. to validate a not-yet-submitted loan request or profile update, which
 * only have raw request input. Keep this in sync with those two methods;
 * they delegate here so the document-applicability behavior is unchanged.
 */
class InstitutionalEmployerCategoryResolver
{
    /**
     * @return 'blgu'|'lgu'|'mrdinc'|'healthcare'|null
     */
    public static function resolve(
        ?string $employerBusinessName,
        ?string $employmentType,
        ?string $natureOfBusiness,
    ): ?string {
        if (self::isBarangayEmployer($employerBusinessName, $employmentType, $natureOfBusiness)) {
            return 'blgu';
        }

        $needle = is_string($employerBusinessName) ? mb_strtolower($employerBusinessName) : '';

        if ($needle !== '' && str_contains($needle, 'mrdinc')) {
            return 'mrdinc';
        }

        if (
            $natureOfBusiness === 'Healthcare'
            || ($needle !== '' && (
                str_contains($needle, 'ldh')
                || str_contains($needle, 'hospital')
                || str_contains($needle, 'medical')
                || str_contains($needle, 'clinic')
            ))
        ) {
            return 'healthcare';
        }

        $isGovernmentSector = $employmentType === 'Government'
            && $natureOfBusiness === 'Government';

        if (
            $isGovernmentSector
            || ($needle !== '' && (
                str_contains($needle, 'lgu')
                || str_contains($needle, 'municipal government')
                || str_contains($needle, 'city government')
                || str_contains($needle, 'provincial government')
            ))
        ) {
            return 'lgu';
        }

        return null;
    }

    /**
     * True when the borrower's income is disbursed through a barangay LGU.
     * Signalled by the employer/business name obviously naming a barangay,
     * or -- for the shorter "brgy"/"bgy" abbreviations, which are too
     * ambiguous to trust on their own -- by the same abbreviations once
     * Employment=Government + Nature of Business=Government confirms the
     * applicant works in the government sector.
     */
    public static function isBarangayEmployer(
        ?string $employerBusinessName,
        ?string $employmentType,
        ?string $natureOfBusiness,
    ): bool {
        if (! is_string($employerBusinessName)) {
            return false;
        }

        $needle = mb_strtolower($employerBusinessName);

        if (str_contains($needle, 'barangay')) {
            return true;
        }

        $isGovernmentSector = $employmentType === 'Government'
            && $natureOfBusiness === 'Government';

        if (! $isGovernmentSector) {
            return false;
        }

        return str_contains($needle, 'brgy') || str_contains($needle, 'bgy');
    }
}

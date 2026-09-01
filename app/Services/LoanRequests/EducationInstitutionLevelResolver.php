<?php

namespace App\Services\LoanRequests;

/**
 * Distinguishes basic-education (elementary/high school, i.e. actual DepEd)
 * employers from tertiary institutions (state universities/colleges, etc.)
 * within the "Government" + "Education" applicant segment, so the salary
 * deduction waiver can print the correct institution name instead of always
 * saying "Dep. Ed." -- a college or university is not DepEd.
 *
 * Mirrors the keyword-matching pattern in InstitutionalEmployerCategoryResolver.
 * Defaults to 'basic' (keeps existing "Dep. Ed." wording) whenever the
 * employer name is missing or ambiguous.
 */
class EducationInstitutionLevelResolver
{
    private const TERTIARY_KEYWORDS = [
        'university',
        'college',
        'polytechnic',
        'institute of technology',
        'state u',
        'suc',
    ];

    /**
     * @return 'basic'|'tertiary'
     */
    public static function resolve(?string $employerBusinessName): string
    {
        if (! is_string($employerBusinessName) || trim($employerBusinessName) === '') {
            return 'basic';
        }

        $needle = mb_strtolower($employerBusinessName);

        foreach (self::TERTIARY_KEYWORDS as $keyword) {
            if (str_contains($needle, $keyword)) {
                return 'tertiary';
            }
        }

        return 'basic';
    }
}

<?php

namespace App\Concerns;

use App\Services\Locations\PsgcService;

/**
 * Normalises raw PSGC location values (city/municipality, province, barangay)
 * to their canonical PSGC form before validation runs. Handles ALL-CAPS,
 * abbreviations, whitespace issues, and minor format differences.
 *
 * Used by FormRequests that validate PSGC fields via ValidPsgcLocality /
 * ValidPsgcProvince / ValidPsgcBarangay rules.
 */
trait ResolvesPsgcFields
{
    /**
     * Resolve all PSGC fields on a person array (applicant / co_maker_1 / co_maker_2).
     *
     * The method mutates the array in-place and returns it for convenience.
     */
    protected function resolvePsgcPersonFields(array $person): array
    {
        $psgc = app(PsgcService::class);

        // ── Birthplace ──────────────────────────────────────────────
        $person['birthplace_city'] = $this->resolveOptional(
            $person['birthplace_city'] ?? null,
            fn (string $v) => $psgc->resolveLocalityName($v),
        );
        $person['birthplace_province'] = $this->resolveOptional(
            $person['birthplace_province'] ?? null,
            fn (string $v) => $psgc->resolveProvinceName($v),
        );

        // ── Home / mailing address ──────────────────────────────────
        $person['address2'] = $this->resolveOptional(
            $person['address2'] ?? null,
            fn (string $v) => $psgc->resolveLocalityName($v),
        );
        $person['address3'] = $this->resolveOptional(
            $person['address3'] ?? null,
            fn (string $v) => $psgc->resolveProvinceName($v),
        );
        $person['address_barangay'] = $this->resolveOptional(
            $person['address_barangay'] ?? null,
            fn (string $v) => $psgc->resolveBarangayName(
                $v,
                $person['address2'] ?? '',
                $person['address3'] ?? null,
            ),
        );

        // ── Employer address ────────────────────────────────────────
        $person['employer_business_address2'] = $this->resolveOptional(
            $person['employer_business_address2'] ?? null,
            fn (string $v) => $psgc->resolveLocalityName($v),
        );
        $person['employer_business_address3'] = $this->resolveOptional(
            $person['employer_business_address3'] ?? null,
            fn (string $v) => $psgc->resolveProvinceName($v),
        );
        $person['employer_business_address_barangay'] = $this->resolveOptional(
            $person['employer_business_address_barangay'] ?? null,
            fn (string $v) => $psgc->resolveBarangayName(
                $v,
                $person['employer_business_address2'] ?? '',
                $person['employer_business_address3'] ?? null,
            ),
        );

        return $person;
    }

    /**
     * Resolve a single PSGC field value. Returns null for null/empty inputs
     * so that nullable fields stay nullable.
     */
    private function resolveOptional(?string $value, callable $resolver): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return $resolver(trim($value));
    }
}

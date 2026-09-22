<?php

namespace App;

/**
 * Explicit, staff/member-selected replacement for the free-text employer
 * name guessing previously done by InstitutionalEmployerCategoryResolver /
 * EducationInstitutionLevelResolver. Drives which deduction-authorization
 * document (Authority to Deduct, DepEd/CHED Waiver, Undertaking-Barangay)
 * applies to a given applicant -- see LoanRequestDocumentCatalog::isApplicable().
 */
enum LoanInstitutionalEmployerCategory: string
{
    case Blgu = 'blgu';
    case Lgu = 'lgu';
    case Mrdinc = 'mrdinc';
    // Value stays 'healthcare' -- renaming the identifier to Ldh reflects
    // that Authority to Deduct only actually applies to Lianga District
    // Hospital employees, not every hospital/clinic. Keeping the backing
    // value unchanged avoids a data migration for existing records.
    case Ldh = 'healthcare';
    case Deped = 'deped';
    case Ched = 'ched';

    public function label(): string
    {
        return match ($this) {
            self::Blgu => 'Barangay Local Government Unit (BLGU)',
            self::Lgu => 'City/Municipal/Provincial Government (LGU)',
            self::Mrdinc => 'MRDINC',
            self::Ldh => 'Lianga District Hospital (LDH)',
            self::Deped => 'DepEd (Basic Education)',
            self::Ched => 'CHED-covered institution (college/university)',
        };
    }

    /**
     * The four categories that route to the Authority to Deduct document
     * (mirrors LoanRequestDocumentCatalog::authorityToDeductGuidance()).
     */
    public function isInstitutionalPayrollCategory(): bool
    {
        return in_array($this, [self::Blgu, self::Lgu, self::Mrdinc, self::Ldh], true);
    }

    /**
     * Fixed, ordered Authority to Deduct signing-officer titles for this
     * category. Staff only type the officer's name -- the title is locked
     * to these exact strings so the printed document is consistent. Empty
     * for Deped/Ched, which use a Waiver document instead of Authority to
     * Deduct (see LoanRequestDocumentCatalog::authorityToDeductCategory()).
     *
     * @return list<string>
     */
    public function authorityToDeductOfficerTitles(): array
    {
        return match ($this) {
            self::Blgu => ['Barangay Treasurer', 'Barangay Captain'],
            self::Lgu => ['Municipal Accountant'],
            self::Mrdinc => ['MRDINC Payroll Maker'],
            self::Ldh => ['Administrative 1/Cashier'],
            self::Deped, self::Ched => [],
        };
    }
}

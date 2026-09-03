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
    case Healthcare = 'healthcare';
    case Deped = 'deped';
    case Ched = 'ched';

    public function label(): string
    {
        return match ($this) {
            self::Blgu => 'Barangay Local Government Unit (BLGU)',
            self::Lgu => 'City/Municipal/Provincial Government (LGU)',
            self::Mrdinc => 'MRDINC',
            self::Healthcare => 'Healthcare institution (hospital, clinic, etc.)',
            self::Deped => 'DepEd (Basic Education)',
            self::Ched => 'CHED-covered institution (college/university)',
        };
    }

    /**
     * The three categories that route to the Authority to Deduct document
     * (mirrors LoanRequestDocumentCatalog::authorityToDeductGuidance()).
     */
    public function isInstitutionalPayrollCategory(): bool
    {
        return in_array($this, [self::Blgu, self::Lgu, self::Mrdinc, self::Healthcare], true);
    }
}

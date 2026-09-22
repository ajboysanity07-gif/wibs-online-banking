<?php

use App\LoanInstitutionalEmployerCategory;

it('keeps the healthcare backing value while relabeling it as Lianga District Hospital (LDH)', function () {
    expect(LoanInstitutionalEmployerCategory::Ldh->value)->toBe('healthcare');
    expect(LoanInstitutionalEmployerCategory::from('healthcare'))->toBe(LoanInstitutionalEmployerCategory::Ldh);
    expect(LoanInstitutionalEmployerCategory::Ldh->label())->toBe('Lianga District Hospital (LDH)');
});

it('returns the fixed Authority to Deduct officer titles for each institutional payroll category', function () {
    expect(LoanInstitutionalEmployerCategory::Blgu->authorityToDeductOfficerTitles())
        ->toBe(['Barangay Treasurer', 'Barangay Captain']);
    expect(LoanInstitutionalEmployerCategory::Lgu->authorityToDeductOfficerTitles())
        ->toBe(['Municipal Accountant']);
    expect(LoanInstitutionalEmployerCategory::Mrdinc->authorityToDeductOfficerTitles())
        ->toBe(['MRDINC Payroll Maker']);
    expect(LoanInstitutionalEmployerCategory::Ldh->authorityToDeductOfficerTitles())
        ->toBe(['Administrative 1/Cashier']);
});

it('returns no fixed officer titles for categories that use a Waiver document instead', function () {
    expect(LoanInstitutionalEmployerCategory::Deped->authorityToDeductOfficerTitles())->toBe([]);
    expect(LoanInstitutionalEmployerCategory::Ched->authorityToDeductOfficerTitles())->toBe([]);
});

it('only routes Blgu, Lgu, Mrdinc, and Ldh to institutional payroll', function () {
    expect(LoanInstitutionalEmployerCategory::Blgu->isInstitutionalPayrollCategory())->toBeTrue();
    expect(LoanInstitutionalEmployerCategory::Lgu->isInstitutionalPayrollCategory())->toBeTrue();
    expect(LoanInstitutionalEmployerCategory::Mrdinc->isInstitutionalPayrollCategory())->toBeTrue();
    expect(LoanInstitutionalEmployerCategory::Ldh->isInstitutionalPayrollCategory())->toBeTrue();
    expect(LoanInstitutionalEmployerCategory::Deped->isInstitutionalPayrollCategory())->toBeFalse();
    expect(LoanInstitutionalEmployerCategory::Ched->isInstitutionalPayrollCategory())->toBeFalse();
});

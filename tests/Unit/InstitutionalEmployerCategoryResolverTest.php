<?php

use App\Services\LoanRequests\InstitutionalEmployerCategoryResolver;

it('resolves LDH from an employer name mentioning Lianga or LDH', function () {
    expect(InstitutionalEmployerCategoryResolver::resolve('Lianga District Hospital', null, null))->toBe('healthcare');
    expect(InstitutionalEmployerCategoryResolver::resolve('LDH Payroll Office', null, null))->toBe('healthcare');
});

it('no longer resolves a generic hospital or clinic name to the healthcare/LDH category', function () {
    expect(InstitutionalEmployerCategoryResolver::resolve('Some Other Hospital', null, 'Healthcare'))->toBeNull();
    expect(InstitutionalEmployerCategoryResolver::resolve('City Medical Clinic', null, 'Healthcare'))->toBeNull();
});

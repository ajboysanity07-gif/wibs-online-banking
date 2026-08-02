<?php

use App\Services\LoanRequests\ApprovedLoanPdfTemplateService;
use App\Services\LoanRequests\PdfFieldMaps\GeneraliPdfFieldMap;

$fieldMap = new GeneraliPdfFieldMap;

$documentData = [
    'applicant' => [
        'last_name' => 'SAMPLE',
        'first_name' => 'MEMBER',
        'middle_name' => 'X',
        'address_line' => 'Zone 1',
        'address_city' => 'Tagum City',
        'address_province' => 'Davao del Norte',
        'mobile' => '09171234567',
        'birthdate' => '1990-01-01',
        'place_of_birth' => 'Tagum',
        'age' => '36',
        'sex' => 'MALE',
        'nationality' => 'FILIPINO',
        'position_or_designation' => 'Teacher I',
        'employer_or_business' => 'DepEd',
        'nature_of_business' => 'Education',
        'office_address' => 'Tagum City',
        'email' => 'x@y.com',
    ],
    'beneficiaries' => [],
    'loan' => [
        'approved_amount' => '25,000.00',
        'approved_term_label' => '12 months',
    ],
    'health' => [
        'health_hypertension' => true,
    ],
    'health_glapi' => [
        'gl_health_q01_weight_change' => true,
        'health_hypertension_details' => 'Controlled',
    ],
    'notarial' => [
        'signing_place' => 'Tagum City',
    ],
];

$service = app(ApprovedLoanPdfTemplateService::class);
$bytes = $service->renderContent('generali.pdf', $documentData, $fieldMap);

$out = 'C:/Users/ACERPR~1/AppData/Local/Temp/opencode/generali-rendered.pdf';
file_put_contents($out, $bytes);

echo 'Wrote '.strlen($bytes)." bytes to $out\n";

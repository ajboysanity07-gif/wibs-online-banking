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
        'gl_health_q02a_neuro' => false,
        'gl_health_q02b_respiratory' => true,
        'gl_health_q02c_cardiac' => true,
        'gl_health_q02d_digestive' => false,
        'gl_health_q02e_diabetes' => false,
        'gl_health_q02e_kidney' => false,
        'gl_health_q02e_liver' => false,
        'gl_health_q02e_urinary' => false,
        'gl_health_q02f_musculoskeletal' => false,
        'gl_health_q02g_oncology_blood' => false,
        'gl_health_q02h_dermatologic' => false,
        'gl_health_q02i_std_viral' => false,
        'gl_health_q02j_other_illness' => false,
        'gl_health_q02b_respiratory_details' => 'Asthma',
        'gl_health_q15_pregnancy' => true,
        'gl_health_q16_relative_pep' => false,
        'gl_health_q17_pending_reinstatement' => true,
        'gl_health_q17_with_glapi' => false,
        'gl_health_q17_with_other_companies' => true,
        'gl_health_q17_with_other_companies_amount' => '100,000.00',
    ],
    'application_form' => [
        'source_of_fund_wealth' => 'Salary',
        'id_type' => 'SSS',
        'id_number' => '12-3456789-0',
        'height_cm' => '165',
        'weight_kg' => '68',
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

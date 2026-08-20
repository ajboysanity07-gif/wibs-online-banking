<?php

use App\Services\LoanRequests\ApprovedLoanPdfTemplateService;
use App\Services\LoanRequests\PdfFieldMaps\GeneraliPdfFieldMap;

$fieldMap = new GeneraliPdfFieldMap;

$documentData = [
    'applicant' => [
        'last_name' => 'CANOY',
        'first_name' => 'RANELIO',
        'middle_name' => 'TORREJANO',
        'address_line' => 'Purok 4',
        'address_street' => 'Purok 4',
        'address_barangay' => 'Poblacion',
        'address_city' => 'Tagbina',
        'address_province' => 'Surigao Del Sur',
        'address_zip' => '8305',
        'mobile' => '09453467589',
        'birthdate' => 'August 28, 1981',
        'place_of_birth' => 'Tagbina Surigao Del Sur, Surigao del Sur',
        'age' => '44',
        'sex' => 'MALE',
        'nationality' => 'FILIPINO',
        'position_or_designation' => 'Team Head',
        'employer_or_business' => 'BDO Network Bank',
        'nature_of_business' => 'Finance',
        'office_address' => 'Purok 4, Poblacion',
        'email' => 'jayreanucell@gmail.com',
        'employer_date_employed' => '2015-03-10',
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

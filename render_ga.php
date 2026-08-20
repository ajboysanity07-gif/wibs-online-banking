<?php

use App\Services\LoanRequests\ApprovedLoanPdfTemplateService;
use App\Services\LoanRequests\PdfFieldMaps\GeneraliApplicationFormPdfFieldMap;

$fieldMap = new GeneraliApplicationFormPdfFieldMap;

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
        'work_phone' => '(086) 555-1234',
        'mobile' => '09453467589',
        'birthdate' => 'August 28, 1981',
        'place_of_birth' => 'Tagbina Surigao Del Sur, Surigao del Sur',
        'age' => '44',
        'sex' => 'MALE',
        'nationality' => 'FILIPINO',
        'civil_status' => 'Married',
        'position_or_designation' => 'Team Head',
        'employer_or_business' => 'BDO Network Bank',
        'nature_of_business' => 'Finance',
        'office_address_line' => 'Purok 4, Poblacion',
        'email' => 'jayreanucell@gmail.com',
    ],
    'application_form' => [
        'cycle_status' => 'New',
        'cycle_number' => '1',
        'employer_date_employed' => '2015-03-10',
        'pep_status' => false,
        'source_of_fund_wealth' => 'Salary',
        'id_type' => 'SSS',
        'id_number' => '12-3456789-0',
    ],
    'beneficiaries' => [
        ['name' => 'MARIA CANOY', 'birthdate' => 'March 3, 1985', 'relationship' => 'Spouse'],
    ],
    'loan' => [
        'approved_amount' => '25,000.00',
        'approved_term_label' => '12 months',
        'approved_date' => 'August 20, 2026',
    ],
    'dependents' => [
        'spouse' => ['name' => 'MARIA CANOY', 'birthdate' => '03/03/1985', 'age' => '41', 'cycle_status' => 'New'],
        'children' => [
            ['name' => 'JUAN CANOY JR', 'birthdate' => '01/01/2010', 'age' => '16', 'cycle_status' => 'New'],
            ['name' => 'ANA CANOY', 'birthdate' => '02/02/2012', 'age' => '14', 'cycle_status' => 'Old'],
            ['name' => 'PEDRO CANOY', 'birthdate' => '03/03/2015', 'age' => '11', 'cycle_status' => 'New'],
        ],
        'siblings' => [
            ['name' => 'JOSE CANOY', 'birthdate' => '04/04/1979', 'age' => '47', 'cycle_status' => 'Old'],
            ['name' => 'LUZ CANOY', 'birthdate' => '05/05/1983', 'age' => '43', 'cycle_status' => 'New'],
            ['name' => 'RIA CANOY', 'birthdate' => '06/06/1986', 'age' => '40', 'cycle_status' => 'Old'],
        ],
        'parents' => [
            ['name' => 'PEDRO CANOY SR', 'birthdate' => '07/07/1955', 'age' => '71', 'cycle_status' => 'New'],
            ['name' => 'LOLITA CANOY', 'birthdate' => '08/08/1958', 'age' => '68', 'cycle_status' => 'Old'],
        ],
        'extended' => [
            ['name' => 'TITA CANOY', 'birthdate' => '09/09/1975', 'age' => '51', 'cycle_status' => 'New'],
            ['name' => 'TITO CANOY', 'birthdate' => '10/10/1972', 'age' => '54', 'cycle_status' => 'Old'],
            ['name' => 'INA CANOY', 'birthdate' => '11/11/1968', 'age' => '58', 'cycle_status' => 'New'],
        ],
    ],
    'notarial' => [
        'signing_place' => 'Tagum City',
    ],
];

$service = app(ApprovedLoanPdfTemplateService::class);
$bytes = $service->renderContent('generali-application-form.pdf', $documentData, $fieldMap);

$out = storage_path('app/tmp/ga-rendered.pdf');
file_put_contents($out, $bytes);

echo 'Wrote '.strlen($bytes)." bytes to $out\n";

<?php

namespace App\Services\LoanRequests\PdfFieldMaps;

class GrepalifePdfFieldMap implements ApprovedLoanPdfFieldMap
{
    public function fields(): array
    {
        return [
            [
                'page' => 1,
                'x' => 11.8,
                'y' => 55.1,
                'size' => 7,
                'width' => 96,
                'line_height' => 3.0,
                'value' => 'applicant.last_name',
            ],
            [
                'page' => 1,
                'x' => 11.8,
                'y' => 62.9,
                'size' => 7,
                'width' => 96,
                'line_height' => 3.0,
                'value' => 'applicant.first_name',
            ],
            [
                'page' => 1,
                'x' => 11.8,
                'y' => 70.6,
                'size' => 7,
                'width' => 96,
                'line_height' => 3.0,
                'value' => 'applicant.middle_name',
            ],
            [
                'type' => 'check',
                'page' => 1,
                'x' => 130.8,
                'y' => 59.9,
                'size' => 6.4,
                'value' => $this->civilStatusChecked('single'),
            ],
            [
                'type' => 'check',
                'page' => 1,
                'x' => 150.5,
                'y' => 59.9,
                'size' => 6.4,
                'value' => $this->civilStatusChecked('married'),
            ],
            [
                'type' => 'check',
                'page' => 1,
                'x' => 169,
                'y' => 59.9,
                'size' => 6.4,
                'value' => $this->civilStatusChecked('widowed'),
            ],
            [
                'type' => 'check',
                'page' => 1,
                'x' => 130.8,
                'y' => 63.8,
                'size' => 6.4,
                'value' => $this->civilStatusChecked('legally separated'),
            ],
            [
                'page' => 1,
                'x' => 100.0,
                'y' => 71.1,
                'size' => 7,
                'width' => 74,
                'line_height' => 2.8,
                'align' => 'C',
                'value' => $this->shortDate('applicant.birthdate'),
            ],
            [
                'page' => 1,
                'x' => 11.8,
                'y' => 78.5,
                'size' => 7,
                'width' => 50,
                'line_height' => 2.4,
                'value' => 'applicant.nationality',
            ],
            [
                'page' => 1,
                'x' => 110.2,
                'y' => 78.5,
                'size' => 7,
                'width' => 86,
                'line_height' => 2.4,
                'value' => 'applicant.place_of_birth',
            ],
            [
                'page' => 1,
                'x' => 11.8,
                'y' => 86.5,
                'size' => 7,
                'width' => 97,
                'line_height' => 1.9,
                'value' => 'applicant.address_line',
            ],
            [
                'page' => 1,
                'x' => 118.0,
                'y' => 86.5,
                'size' => 7,
                'width' => 24,
                'line_height' => 1.9,
                'value' => 'applicant.address_city',
            ],
            [
                'page' => 1,
                'x' => 143.3,
                'y' => 86.5,
                'size' => 7,
                'width' => 25,
                'line_height' => 1.9,
                'value' => 'applicant.address_province',
            ],
            [
                'page' => 1,
                'x' => 171.7,
                'y' => 86.5,
                'size' => 7,
                'width' => 17,
                'line_height' => 1.9,
                'value' => static function (array $documentData) {
                    return data_get($documentData, 'applicant.address_country') ?? 'Philippines';
                },
            ],
            [
                'page' => 1,
                'x' => 191.7,
                'y' => 86.5,
                'size' => 7,
                'width' => 11,
                'line_height' => 1.9,
                'value' => 'applicant.address_zip',
            ],
            [
                'page' => 1,
                'x' => 11.8,
                'y' => 94,
                'size' => 7,
                'width' => 45,
                'line_height' => 2.0,
                'value' => 'applicant.employer_or_business',
            ],
            [
                'page' => 1,
                'x' => 61,
                'y' => 94,
                'size' => 7,
                'width' => 56,
                'line_height' => 2.0,
                'value' => 'applicant.nature_of_business',
            ],
            [
                'page' => 1,
                'x' => 118.0,
                'y' => 94,
                'size' => 7,
                'width' => 32,
                'line_height' => 1.9,
                'value' => 'applicant.position_or_designation',
            ],
            [
                'page' => 1,
                'x' => 155.5,
                'y' => 94,
                'size' => 7,
                'width' => 50,
                'line_height' => 2.0,
                'value' => 'applicant.years_in_work_business',
            ],
            [
                'page' => 1,
                'x' => 11.8,
                'y' => 102.5,
                'size' => 7,
                'width' => 97,
                'line_height' => 1.8,
                'value' => 'applicant.office_address',
            ],
            [
                'page' => 1,
                'x' => 118.0,
                'y' => 102.5,
                'size' => 7,
                'width' => 24,
                'line_height' => 1.8,
                'value' => 'applicant.office_city',
            ],
            [
                'page' => 1,
                'x' => 146.3,
                'y' => 102.5,
                'size' => 7,
                'width' => 23,
                'line_height' => 1.8,
                'value' => 'applicant.office_province',
            ],
            [
                'page' => 1,
                'x' => 171.7,
                'y' => 102.5,
                'size' => 7,
                'width' => 17,
                'line_height' => 1.8,
                'value' => static function (array $documentData) {
                    return data_get($documentData, 'applicant.office_country') ?? 'Philippines';
                },
            ],
            [
                'page' => 1,
                'x' => 191.7,
                'y' => 102.5,
                'size' => 7,
                'width' => 11,
                'line_height' => 1.8,
                'value' => 'applicant.office_zip',
            ],
            [
                'page' => 1,
                'x' => 11.8,
                'y' => 111.5,
                'size' => 7,
                'width' => 44,
                'line_height' => 2.0,
                'value' => 'applicant.home_phone',
            ],
            [
                'page' => 1,
                'x' => 60.6,
                'y' => 111.5,
                'size' => 7,
                'width' => 48,
                'line_height' => 2.0,
                'value' => 'applicant.work_phone',
            ],
            [
                'page' => 1,
                'x' => 110.5,
                'y' => 111.5,
                'size' => 7,
                'width' => 41,
                'line_height' => 2.1,
                'value' => 'applicant.mobile',
            ],
            [
                'page' => 1,
                'x' => 159.6,
                'y' => 111.5,
                'size' => 7,
                'width' => 42,
                'line_height' => 1.8,
                'value' => 'applicant.email',
            ],
            [
                'page' => 1,
                'x' => 11.8,
                'y' => 119.8,
                'size' => 7,
                'width' => 94,
                'line_height' => 2.2,
                'value' => 'organization.company_name',
                'transform' => $this->upperTransform(),
            ],
            [
                'page' => 1,
                'x' => 97.5,
                'y' => 119.8,
                'size' => 7,
                'width' => 40,
                'line_height' => 2.2,
                'align' => 'C',
                'value' => 'loan.approved_term_label',
            ],
            [
                'page' => 1,
                'x' => 145.6,
                'y' => 119.8,
                'size' => 7,
                'width' => 42,
                'line_height' => 2.2,
                'align' => 'C',
                'value' => 'loan.approved_amount',
            ],
            [
                'type' => 'check',
                'page' => 1,
                'x' => 68.5,
                'y' => 125.0,
                'size' => 6.4,
                'value' => $this->hasExistingLoanDetails(),
            ],
            [
                'page' => 1,
                'x' => 71.5,
                'y' => 134.1,
                'size' => 7,
                'width' => 40,
                'line_height' => 2.1,
                'align' => 'C',
                'value' => 'loan.approved_date_short',
            ],
            [
                'page' => 1,
                'x' => 118.5,
                'y' => 134.1,
                'size' => 7,
                'width' => 36,
                'line_height' => 2.1,
                'align' => 'C',
                'value' => 'loan.type',
            ],
            [
                'page' => 1,
                'x' => 170.2,
                'y' => 134.1,
                'size' => 7,
                'width' => 24,
                'line_height' => 2.1,
                'align' => 'C',
                'value' => 'loan.approved_amount',
            ],
            [
                'page' => 1,
                'x' => 15.0,
                'y' => 149.2,
                'size' => 7,
                'width' => 90,
                'line_height' => 1.8,
                'value' => 'beneficiaries.0.name',
            ],
            [
                'page' => 1,
                'x' => 111.0,
                'y' => 149.2,
                'size' => 5.0,
                'width' => 27,
                'line_height' => 1.8,
                'align' => 'C',
                'value' => 'beneficiaries.0.birthdate',
            ],
            [
                'page' => 1,
                'x' => 150.0,
                'y' => 149.2,
                'size' => 5.0,
                'width' => 45,
                'line_height' => 1.8,
                'value' => 'beneficiaries.0.relationship',
            ],
            [
                'page' => 1,
                'x' => 15.0,
                'y' => 152.8,
                'size' => 5.1,
                'width' => 90,
                'line_height' => 1.8,
                'value' => 'beneficiaries.1.name',
            ],
            [
                'page' => 1,
                'x' => 111.0,
                'y' => 152.8,
                'size' => 5.0,
                'width' => 27,
                'line_height' => 1.8,
                'align' => 'C',
                'value' => 'beneficiaries.1.birthdate',
            ],
            [
                'page' => 1,
                'x' => 150.0,
                'y' => 152.8,
                'size' => 5.0,
                'width' => 45,
                'line_height' => 1.8,
                'value' => 'beneficiaries.1.relationship',
            ],
            [
                'page' => 1,
                'x' => 15.0,
                'y' => 156.4,
                'size' => 5.1,
                'width' => 90,
                'line_height' => 1.8,
                'value' => 'beneficiaries.2.name',
            ],
            [
                'page' => 1,
                'x' => 111.0,
                'y' => 156.4,
                'size' => 5.0,
                'width' => 27,
                'line_height' => 1.8,
                'align' => 'C',
                'value' => 'beneficiaries.2.birthdate',
            ],
            [
                'page' => 1,
                'x' => 150.0,
                'y' => 156.4,
                'size' => 5.0,
                'width' => 45,
                'line_height' => 1.8,
                'value' => 'beneficiaries.2.relationship',
            ],
            [
                'type' => 'signature',
                'page' => 2,
                'x' => 14.0,
                'y' => 82.7,
                'width' => 44,
                'height' => 6.2,
                'scale' => 2.0,
                'max_width' => 68.0,
                'max_height' => 11.5,
                'offset_x' => -2.0,
                'offset_y' => -1.0,
                'value' => 'applicant.signature_path',
            ],
            [
                'page' => 2,
                'x' => 71,
                'y' => 81.5,
                'size' => 7.2,
                'width' => 118,
                'line_height' => 2.5,
                'value' => 'applicant.full_name',
                'transform' => $this->upperTransform(),
            ],
            [
                'type' => 'signature',
                'page' => 2,
                'x' => 14.0,
                'y' => 91.7,
                'width' => 44,
                'height' => 6.2,
                'scale' => 2.0,
                'max_width' => 68.0,
                'max_height' => 11.5,
                'offset_x' => -2.0,
                'offset_y' => -1.0,
                'value' => 'reviewer.signature_path',
            ],
            [
                'page' => 2,
                'x' => 71,
                'y' => 91.5,
                'size' => 7.0,
                'width' => 62,
                'line_height' => 2.3,
                'align' => 'L',
                'value' => 'reviewer.name',
                'transform' => $this->upperTransform(),
            ],
            [
                'page' => 2,
                'x' => 141.5,
                'y' => 91.5,
                'size' => 7.0,
                'width' => 62,
                'line_height' => 2.3,
                'align' => 'L',
                'value' => 'organization.company_name',
                'transform' => $this->upperTransform(),
            ],
            [
                'page' => 2,
                'x' => 15.0,
                'y' => 101.5,
                'size' => 7.0,
                'width' => 86,
                'line_height' => 2.3,
                'align' => 'L',
                'value' => 'organization.business_address',
                'transform' => $this->upperTransform(),
            ],
            [
                'page' => 2,
                'x' => 108.8,
                'y' => 101.5,
                'size' => 7.0,
                'width' => 44,
                'line_height' => 2.3,
                'align' => 'L',
                'value' => 'loan.approved_date_short',
            ],
        ];
    }

    private function civilStatusChecked(string $expected): callable
    {
        return static function (array $documentData) use ($expected): bool {
            $actual = strtolower(trim((string) data_get($documentData, 'applicant.civil_status')));
            $normalizedExpected = strtolower(trim($expected));

            if ($actual === '') {
                return false;
            }

            return match ($normalizedExpected) {
                'single' => $actual === 'single',
                'married' => $actual === 'married',
                'widowed' => in_array($actual, ['widowed', 'widow'], true),
                'legally separated' => in_array(
                    $actual,
                    ['legally separated', 'separated', 'legal separated'],
                    true,
                ),
                default => false,
            };
        };
    }

    private function shortDate(string $path): callable
    {
        return static function (array $documentData) use ($path): ?string {
            $value = data_get($documentData, $path);

            if (! is_string($value) || trim($value) === '') {
                return null;
            }

            $timestamp = strtotime($value);

            if ($timestamp === false) {
                return $value;
            }

            return date('m/d/Y', $timestamp);
        };
    }

    private function hasExistingLoanDetails(): callable
    {
        return static function (array $documentData): bool {
            return data_get($documentData, 'loan.approved_date_short') !== null
                || data_get($documentData, 'loan.type') !== null
                || data_get($documentData, 'loan.approved_amount') !== null;
        };
    }

    private function upper(?string $value): string
    {
        return mb_strtoupper(trim((string) $value));
    }

    private function upperTransform(): callable
    {
        return fn (mixed $value): string => $this->upper(
            is_scalar($value) ? (string) $value : null,
        );
    }
}

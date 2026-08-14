<?php

namespace App\Services\LoanRequests\PdfFieldMaps;

use App\Services\LoanRequests\PdfFieldMaps\Concerns\UppercasesFieldValues;

class GrepalifePdfFieldMap implements ApprovedLoanPdfFieldMap
{
    use UppercasesFieldValues;

    /**
     * Section 2 health questionnaire Yes/No checkbox columns (page 1). These are
     * the top-left corners of the template's checkbox squares on
     * grepalife-page-1.png, matching how the civil-status/declaration checks are
     * anchored (box top-left, check drawn downward inside the square).
     */
    private const HEALTH_Y_X = 163.78;

    private const HEALTH_N_X = 176.48;

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
                'shrink_to_fit' => true,
                'min_size' => 5.0,
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
                'value' => 'applicant.office_address_line',
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
                'shrink_to_fit' => true,
                'min_size' => 5.0,
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
                'transform' => $this->pesoTransform(),
            ],
            [
                'type' => 'check',
                'page' => 1,
                'x' => 68.5,
                'y' => 125.0,
                'size' => 6.4,
                'value' => $this->healthChecked('declaration_existing_loans', 'declarations'),
            ],
            // Existing/previous loan table (section 1.1), 3 repeatable rows bound
            // to existing_loans.{0,1,2}.{date,type,amount} -- see
            // ApprovedLoanDocumentService::existingLoansDocumentData(). The table
            // body is a single empty box (y ~133.3-143.6, no internal grid lines),
            // so the 3 rows are evenly spaced at 3.6mm from row 1's calibrated
            // baseline (134.1), keeping all three inside the box.
            [
                'page' => 1,
                'x' => 71.5,
                'y' => 134.1,
                'size' => 7,
                'width' => 40,
                'line_height' => 2.1,
                'align' => 'C',
                'value' => $this->shortDate('existing_loans.0.date'),
            ],
            [
                'page' => 1,
                'x' => 118.5,
                'y' => 134.1,
                'size' => 7,
                'width' => 36,
                'line_height' => 2.1,
                'align' => 'C',
                'value' => 'existing_loans.0.type',
            ],
            [
                'page' => 1,
                'x' => 170.2,
                'y' => 134.1,
                'size' => 7,
                'width' => 24,
                'line_height' => 2.1,
                'align' => 'C',
                'value' => 'existing_loans.0.amount',
                'transform' => $this->pesoTransform(),
            ],
            [
                'page' => 1,
                'x' => 71.5,
                'y' => 137.7,
                'size' => 7,
                'width' => 40,
                'line_height' => 2.1,
                'align' => 'C',
                'value' => $this->shortDate('existing_loans.1.date'),
            ],
            [
                'page' => 1,
                'x' => 118.5,
                'y' => 137.7,
                'size' => 7,
                'width' => 36,
                'line_height' => 2.1,
                'align' => 'C',
                'value' => 'existing_loans.1.type',
            ],
            [
                'page' => 1,
                'x' => 170.2,
                'y' => 137.7,
                'size' => 7,
                'width' => 24,
                'line_height' => 2.1,
                'align' => 'C',
                'value' => 'existing_loans.1.amount',
                'transform' => $this->pesoTransform(),
            ],
            [
                'page' => 1,
                'x' => 71.5,
                'y' => 141.3,
                'size' => 7,
                'width' => 40,
                'line_height' => 2.1,
                'align' => 'C',
                'value' => $this->shortDate('existing_loans.2.date'),
            ],
            [
                'page' => 1,
                'x' => 118.5,
                'y' => 141.3,
                'size' => 7,
                'width' => 36,
                'line_height' => 2.1,
                'align' => 'C',
                'value' => 'existing_loans.2.type',
            ],
            [
                'page' => 1,
                'x' => 170.2,
                'y' => 141.3,
                'size' => 7,
                'width' => 24,
                'line_height' => 2.1,
                'align' => 'C',
                'value' => 'existing_loans.2.amount',
                'transform' => $this->pesoTransform(),
            ],
            [
                'page' => 1,
                'x' => 15.0,
                'y' => 157.0,
                'size' => 7,
                'width' => 90,
                'line_height' => 2.1,
                'value' => 'beneficiaries.0.name',
            ],
            [
                'page' => 1,
                'x' => 111.0,
                'y' => 157.0,
                'size' => 7,
                'width' => 27,
                'line_height' => 2.1,
                'align' => 'C',
                'value' => 'beneficiaries.0.birthdate',
            ],
            [
                'page' => 1,
                'x' => 150.0,
                'y' => 157.0,
                'size' => 7,
                'width' => 45,
                'line_height' => 2.1,
                'value' => 'beneficiaries.0.relationship',
            ],
            [
                'page' => 1,
                'x' => 15.0,
                'y' => 160.6,
                'size' => 7,
                'width' => 90,
                'line_height' => 2.1,
                'value' => 'beneficiaries.1.name',
            ],
            [
                'page' => 1,
                'x' => 111.0,
                'y' => 160.6,
                'size' => 7,
                'width' => 27,
                'line_height' => 2.1,
                'align' => 'C',
                'value' => 'beneficiaries.1.birthdate',
            ],
            [
                'page' => 1,
                'x' => 150.0,
                'y' => 160.6,
                'size' => 7,
                'width' => 45,
                'line_height' => 2.1,
                'value' => 'beneficiaries.1.relationship',
            ],
            [
                'page' => 1,
                'x' => 15.0,
                'y' => 164.2,
                'size' => 7,
                'width' => 90,
                'line_height' => 2.1,
                'value' => 'beneficiaries.2.name',
            ],
            [
                'page' => 1,
                'x' => 111.0,
                'y' => 164.2,
                'size' => 7,
                'width' => 27,
                'line_height' => 2.1,
                'align' => 'C',
                'value' => 'beneficiaries.2.birthdate',
            ],
            [
                'page' => 1,
                'x' => 150.0,
                'y' => 164.2,
                'size' => 7,
                'width' => 45,
                'line_height' => 2.1,
                'value' => 'beneficiaries.2.relationship',
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
            // Section 2 -- Health questionnaire (page 1). Each of Q1-Q4 is a Yes/No
            // checkbox pair in the template's right-hand columns (grepalife-page-1.png).
            // Coordinates are the detected top-left corners of the four checkbox
            // squares: Yes at x=163.78, No at x=176.48, rows at y = 192.26 / 200.88 /
            // 212.80 / 218.89. An affirmative answer checks Yes, an explicit negative
            // checks No, and an unanswered question leaves both boxes blank.
            ...$this->healthYesNoRow($this->healthSmokingAnswer(), 192.26),
            ...$this->healthYesNoRow($this->healthAnswer('health_hypertension'), 200.88),
            ...$this->healthYesNoRow($this->healthAnswer('gl_health_q02e_diabetes', 'health_glapi'), 212.80),
            ...$this->healthYesNoRow($this->healthAnswer('health_recent_hospitalization', 'health_glapi'), 218.89),
        ];
    }

    private function healthChecked(string $key, string $section = 'health'): callable
    {
        return static function (array $documentData) use ($key, $section): bool {
            $value = data_get($documentData, $section.'.'.$key);

            if ($value === null || $value === false || $value === 0 || $value === '') {
                return false;
            }

            if (is_string($value)) {
                return in_array(strtolower(trim($value)), ['yes', '1', 'true'], true);
            }

            return (bool) $value;
        };
    }

    /**
     * A single Yes/No checkbox pair for one health question, mirroring the
     * Generali map's === true / === false convention so an unanswered question
     * leaves both boxes blank.
     *
     * @return list<array<string, mixed>>
     */
    private function healthYesNoRow(callable $answer, float $y): array
    {
        return [
            [
                'type' => 'check',
                'page' => 1,
                'x' => self::HEALTH_Y_X,
                'y' => $y,
                'size' => 6.4,
                'value' => static fn (array $d): bool => $answer($d) === true,
            ],
            [
                'type' => 'check',
                'page' => 1,
                'x' => self::HEALTH_N_X,
                'y' => $y,
                'size' => 6.4,
                'value' => static fn (array $d): bool => $answer($d) === false,
            ],
        ];
    }

    /**
     * Tri-state health answer: true (Yes), false (No), null (unanswered).
     *
     * @return callable(array<string, mixed>): ?bool
     */
    private function healthAnswer(string $key, string $section = 'health'): callable
    {
        return static function (array $documentData) use ($key, $section): ?bool {
            $value = data_get($documentData, $section.'.'.$key);

            if ($value === null || $value === '' || $value === 0) {
                return null;
            }

            if (is_string($value)) {
                return in_array(strtolower(trim($value)), ['yes', '1', 'true'], true);
            }

            return (bool) $value;
        };
    }

    /**
     * GREPALIFE's smoker answer is derived from the wizard's 3-value
     * health_smoking_status field -- 'none' answers No, any other value
     * (light/heavy) checks Yes.
     *
     * @return callable(array<string, mixed>): ?bool
     */
    private function healthSmokingAnswer(): callable
    {
        return static function (array $documentData): ?bool {
            $status = data_get($documentData, 'health.health_smoking_status');

            if (! is_string($status) || trim($status) === '') {
                return null;
            }

            return $status !== 'none';
        };
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

    private function pesoTransform(): callable
    {
        return fn (mixed $value): ?string => self::formatPesoAmount($value);
    }

    /**
     * Peso-prefixed currency formatting for the loan amount fields, e.g.
     * "P100,000.00" or "P15,000.50". Strips any existing thousands separators
     * before re-formatting so already-formatted values ("100,000.00") and raw
     * values ("15000.5") both land on the same output.
     */
    private static function formatPesoAmount(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $clean = preg_replace('/[^\d.]/', '', (string) $value);

        if ($clean === null || ! is_numeric($clean)) {
            return null;
        }

        return 'P'.number_format((float) $clean, 2, '.', ',');
    }
}

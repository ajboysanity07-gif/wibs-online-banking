<?php

namespace App\Services\LoanRequests;

use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\Models\LoanRequest;
use App\Models\LoanRequestPerson;
use App\Services\LoanRequests\PdfFieldMaps\AffidavitUndertakingPdfFieldMap;
use App\Services\LoanRequests\PdfFieldMaps\AuthorizationPdfFieldMap;
use App\Services\LoanRequests\PdfFieldMaps\GrepalifePdfFieldMap;
use App\Services\LoanRequests\PdfFieldMaps\LoanSecurityAgreementPdfFieldMap;
use App\Services\LoanRequests\PdfFieldMaps\UndertakingBarangayPdfFieldMap;
use App\Services\OrganizationSettingsService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use NumberFormatter;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use ZipArchive;

class ApprovedLoanDocumentService
{
    private const PDF_TEMPLATE_FILENAMES = [
        'grepalife' => 'grepalife.pdf',
        'loan_security_agreement' => 'loan-security-agreement.pdf',
        'undertaking_barangay' => 'undertaking-barangay-officials.pdf',
        'affidavit_undertaking' => 'affidavit-undertaking.pdf',
        'authorization' => 'authorization.pdf',
    ];

    private const EXCEL_TEMPLATE_FILENAMES = [
        'plan_of_payment' => 'plan-of-payment-disclosure-promissory-note.xlsx',
    ];

    /**
     * @var array<string, string>
     */
    private const ZIP_DOCUMENT_NAMES = [
        'application_form' => '01-Application-Form.pdf',
        'grepalife' => '02-GREPALIFE.pdf',
        'loan_security_agreement' => '03-Loan-Security-Agreement.pdf',
        'undertaking_barangay' => '04-Undertaking-Barangay-Officials.pdf',
        'affidavit_undertaking' => '05-Affidavit-of-Undertaking.pdf',
        'authorization' => '06-Authorization.pdf',
        'plan_of_payment' => '07-Plan-of-Payment-Disclosure-Promissory-Note.xlsx',
    ];

    /**
     * @var array<string, string>
     */
    private const DOWNLOAD_DOCUMENT_NAMES = [
        'application_form' => 'application-form-%s.pdf',
        'grepalife' => 'grepalife-%s.pdf',
        'loan_security_agreement' => 'loan-security-agreement-%s.pdf',
        'undertaking_barangay' => 'undertaking-barangay-%s.pdf',
        'affidavit_undertaking' => 'affidavit-undertaking-%s.pdf',
        'authorization' => 'authorization-%s.pdf',
        'plan_of_payment' => 'plan-of-payment-disclosure-promissory-note-%s.xlsx',
    ];

    public function __construct(
        private LoanRequestPdfService $loanRequestPdfService,
        private OrganizationSettingsService $organizationSettingsService,
        private ApprovedLoanPdfTemplateService $approvedLoanPdfTemplateService,
        private ApprovedLoanExcelTemplateService $approvedLoanExcelTemplateService,
        private GrepalifePdfFieldMap $grepalifePdfFieldMap,
        private LoanSecurityAgreementPdfFieldMap $loanSecurityAgreementPdfFieldMap,
        private UndertakingBarangayPdfFieldMap $undertakingBarangayPdfFieldMap,
        private AffidavitUndertakingPdfFieldMap $affidavitUndertakingPdfFieldMap,
        private AuthorizationPdfFieldMap $authorizationPdfFieldMap,
    ) {}

    public function applicationForm(LoanRequest $loanRequest): Response
    {
        $loanRequest->loadMissing('people', 'reviewedBy', 'user');

        $workingDirectory = $this->makeWorkingDirectory($loanRequest);
        $applicationFormPdfPath = $workingDirectory
            .DIRECTORY_SEPARATOR
            .self::ZIP_DOCUMENT_NAMES['application_form'];

        try {
            $this->loanRequestPdfService->saveToPath(
                $loanRequest,
                $applicationFormPdfPath,
            );
        } catch (Throwable $exception) {
            File::deleteDirectory($workingDirectory);
            throw $exception;
        }

        return $this->downloadFile(
            $applicationFormPdfPath,
            $this->buildDownloadFilename('application_form', $loanRequest),
            'application/pdf',
            $workingDirectory,
        );
    }

    public function grepalife(LoanRequest $loanRequest): Response
    {
        $this->ensureApproved($loanRequest);
        $loanRequest->loadMissing('people', 'reviewedBy', 'user');

        return $this->approvedLoanPdfTemplateService->renderResponse(
            self::PDF_TEMPLATE_FILENAMES['grepalife'],
            $this->buildDownloadFilename('grepalife', $loanRequest),
            $this->buildDocumentData($loanRequest),
            $this->grepalifePdfFieldMap,
            'inline',
        );
    }

    public function loanSecurityAgreement(LoanRequest $loanRequest): Response
    {
        return $this->downloadApprovedDocument(
            $loanRequest,
            'loan_security_agreement',
            'application/pdf',
            function (string $outputPath, array $documentData): void {
                $this->approvedLoanPdfTemplateService->generate(
                    self::PDF_TEMPLATE_FILENAMES['loan_security_agreement'],
                    $outputPath,
                    $documentData,
                    $this->loanSecurityAgreementPdfFieldMap,
                );
            },
        );
    }

    public function undertakingBarangay(LoanRequest $loanRequest): Response
    {
        return $this->downloadApprovedDocument(
            $loanRequest,
            'undertaking_barangay',
            'application/pdf',
            function (string $outputPath, array $documentData): void {
                $this->approvedLoanPdfTemplateService->generate(
                    self::PDF_TEMPLATE_FILENAMES['undertaking_barangay'],
                    $outputPath,
                    $documentData,
                    $this->undertakingBarangayPdfFieldMap,
                );
            },
        );
    }

    public function affidavitUndertaking(LoanRequest $loanRequest): Response
    {
        return $this->downloadApprovedDocument(
            $loanRequest,
            'affidavit_undertaking',
            'application/pdf',
            function (string $outputPath, array $documentData): void {
                $this->approvedLoanPdfTemplateService->generate(
                    self::PDF_TEMPLATE_FILENAMES['affidavit_undertaking'],
                    $outputPath,
                    $documentData,
                    $this->affidavitUndertakingPdfFieldMap,
                );
            },
        );
    }

    public function authorization(LoanRequest $loanRequest): Response
    {
        return $this->downloadApprovedDocument(
            $loanRequest,
            'authorization',
            'application/pdf',
            function (string $outputPath, array $documentData): void {
                $this->approvedLoanPdfTemplateService->generate(
                    self::PDF_TEMPLATE_FILENAMES['authorization'],
                    $outputPath,
                    $documentData,
                    $this->authorizationPdfFieldMap,
                );
            },
        );
    }

    public function planOfPayment(LoanRequest $loanRequest): Response
    {
        return $this->downloadApprovedDocument(
            $loanRequest,
            'plan_of_payment',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            function (string $outputPath, array $documentData): void {
                $this->approvedLoanExcelTemplateService->generate(
                    self::EXCEL_TEMPLATE_FILENAMES['plan_of_payment'],
                    $outputPath,
                    $documentData,
                );
            },
        );
    }

    public function packageZip(LoanRequest $loanRequest): Response
    {
        $this->ensureApproved($loanRequest);
        $loanRequest->loadMissing('people', 'reviewedBy', 'user');

        $workingDirectory = $this->makeWorkingDirectory($loanRequest);
        $documentDirectory = $workingDirectory.DIRECTORY_SEPARATOR.'documents';
        File::ensureDirectoryExists($documentDirectory);

        try {
            $documentData = $this->buildDocumentData($loanRequest);

            $applicationFormPath = $documentDirectory.DIRECTORY_SEPARATOR.self::ZIP_DOCUMENT_NAMES['application_form'];
            $grepalifePath = $documentDirectory.DIRECTORY_SEPARATOR.self::ZIP_DOCUMENT_NAMES['grepalife'];
            $loanSecurityAgreementPath = $documentDirectory.DIRECTORY_SEPARATOR.self::ZIP_DOCUMENT_NAMES['loan_security_agreement'];
            $undertakingBarangayPath = $documentDirectory.DIRECTORY_SEPARATOR.self::ZIP_DOCUMENT_NAMES['undertaking_barangay'];
            $affidavitUndertakingPath = $documentDirectory.DIRECTORY_SEPARATOR.self::ZIP_DOCUMENT_NAMES['affidavit_undertaking'];
            $authorizationPath = $documentDirectory.DIRECTORY_SEPARATOR.self::ZIP_DOCUMENT_NAMES['authorization'];
            $planOfPaymentPath = $documentDirectory.DIRECTORY_SEPARATOR.self::ZIP_DOCUMENT_NAMES['plan_of_payment'];

            $this->loanRequestPdfService->saveToPath($loanRequest, $applicationFormPath);
            $this->approvedLoanPdfTemplateService->generate(
                self::PDF_TEMPLATE_FILENAMES['grepalife'],
                $grepalifePath,
                $documentData,
                $this->grepalifePdfFieldMap,
            );
            $this->approvedLoanPdfTemplateService->generate(
                self::PDF_TEMPLATE_FILENAMES['loan_security_agreement'],
                $loanSecurityAgreementPath,
                $documentData,
                $this->loanSecurityAgreementPdfFieldMap,
            );
            $this->approvedLoanPdfTemplateService->generate(
                self::PDF_TEMPLATE_FILENAMES['undertaking_barangay'],
                $undertakingBarangayPath,
                $documentData,
                $this->undertakingBarangayPdfFieldMap,
            );
            $this->approvedLoanPdfTemplateService->generate(
                self::PDF_TEMPLATE_FILENAMES['affidavit_undertaking'],
                $affidavitUndertakingPath,
                $documentData,
                $this->affidavitUndertakingPdfFieldMap,
            );
            $this->approvedLoanPdfTemplateService->generate(
                self::PDF_TEMPLATE_FILENAMES['authorization'],
                $authorizationPath,
                $documentData,
                $this->authorizationPdfFieldMap,
            );
            $this->approvedLoanExcelTemplateService->generate(
                self::EXCEL_TEMPLATE_FILENAMES['plan_of_payment'],
                $planOfPaymentPath,
                $documentData,
            );

            $zipFilename = sprintf(
                'approved-loan-documents-%s.zip',
                $this->normalizeReferenceForFilename($loanRequest->reference),
            );
            $zipPath = $workingDirectory.DIRECTORY_SEPARATOR.$zipFilename;

            $this->createZipArchive($zipPath, [
                $applicationFormPath,
                $grepalifePath,
                $loanSecurityAgreementPath,
                $undertakingBarangayPath,
                $affidavitUndertakingPath,
                $authorizationPath,
                $planOfPaymentPath,
            ]);
        } catch (Throwable $exception) {
            File::deleteDirectory($workingDirectory);
            throw $exception;
        }

        return $this->downloadFile(
            $zipPath,
            $zipFilename,
            'application/zip',
            $workingDirectory,
        );
    }

    /**
     * @param  callable(string, array<string, mixed>): void  $generator
     */
    private function downloadApprovedDocument(
        LoanRequest $loanRequest,
        string $documentKey,
        string $contentType,
        callable $generator,
    ): Response {
        $this->ensureApproved($loanRequest);
        $loanRequest->loadMissing('people', 'reviewedBy', 'user');

        $workingDirectory = $this->makeWorkingDirectory($loanRequest);
        $outputPath = $workingDirectory
            .DIRECTORY_SEPARATOR
            .self::ZIP_DOCUMENT_NAMES[$documentKey];

        try {
            $documentData = $this->buildDocumentData($loanRequest);
            $generator($outputPath, $documentData);
        } catch (Throwable $exception) {
            File::deleteDirectory($workingDirectory);
            throw $exception;
        }

        return $this->downloadFile(
            $outputPath,
            $this->buildDownloadFilename($documentKey, $loanRequest),
            $contentType,
            $workingDirectory,
        );
    }

    private function buildDownloadFilename(
        string $documentKey,
        LoanRequest $loanRequest,
    ): string {
        $format = self::DOWNLOAD_DOCUMENT_NAMES[$documentKey] ?? '%s';

        return sprintf(
            $format,
            $this->normalizeReferenceForFilename($loanRequest->reference),
        );
    }

    private function downloadFile(
        string $filePath,
        string $filename,
        string $contentType,
        string $workingDirectory,
    ): BinaryFileResponse {
        if (! app()->runningUnitTests()) {
            app()->terminating(static function () use ($workingDirectory): void {
                File::deleteDirectory($workingDirectory);
            });
        }

        return response()
            ->download($filePath, $filename, [
                'Content-Type' => $contentType,
            ])
            ->deleteFileAfterSend(true);
    }

    private function ensureApproved(LoanRequest $loanRequest): void
    {
        $status = $loanRequest->status instanceof LoanRequestStatus
            ? $loanRequest->status->value
            : (string) $loanRequest->status;

        if ($status !== LoanRequestStatus::Approved->value) {
            throw new RuntimeException(
                'Approved loan documents are only available for approved loan requests.',
            );
        }
    }

    private function makeWorkingDirectory(LoanRequest $loanRequest): string
    {
        $workingDirectory = storage_path(
            sprintf(
                'app/tmp/approved-loan-documents/%s-%s',
                $loanRequest->id,
                Str::uuid(),
            ),
        );

        File::ensureDirectoryExists($workingDirectory);

        return $workingDirectory;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDocumentData(LoanRequest $loanRequest): array
    {
        $applicant = $this->resolvePerson($loanRequest, LoanRequestPersonRole::Applicant);
        $coMakerOne = $this->resolvePerson($loanRequest, LoanRequestPersonRole::CoMakerOne);
        $coMakerTwo = $this->resolvePerson($loanRequest, LoanRequestPersonRole::CoMakerTwo);
        $branding = $this->organizationSettingsService->branding();
        $approvedAt = $loanRequest->reviewed_at instanceof Carbon
            ? $loanRequest->reviewed_at
            : now();
        $approvedTerm = $this->normalizeIntegerValue($loanRequest->approved_term);
        $approvedAmountRaw = $this->normalizeNumericValue($loanRequest->approved_amount);
        $paymentMode = $this->resolveWorkbookPaymentMode($applicant);
        $maturityDate = $this->resolveMaturityDate($approvedAt, $approvedTerm);

        return [
            'organization' => [
                'company_name' => $this->normalizeText($branding['companyName'] ?? null),
            ],
            'reviewer' => [
                'name' => $this->normalizeText($loanRequest->reviewedBy?->name),
                'position' => null,
            ],
            'loan' => [
                'reference' => $loanRequest->reference,
                'type' => $this->normalizeText($loanRequest->loan_type_label_snapshot),
                'requested_amount' => $this->formatCurrencyValue($loanRequest->requested_amount),
                'requested_amount_raw' => $this->normalizeNumericValue($loanRequest->requested_amount),
                'approved_amount' => $this->formatCurrencyValue($loanRequest->approved_amount),
                'approved_amount_raw' => $approvedAmountRaw,
                'approved_amount_words' => $this->formatCurrencyWords($approvedAmountRaw),
                'approved_term' => $loanRequest->approved_term,
                'approved_term_raw' => $approvedTerm,
                'approved_term_label' => $loanRequest->approved_term !== null
                    ? $loanRequest->approved_term.' months'
                    : null,
                'amortization_count' => $this->resolveAmortizationCount(
                    $approvedTerm,
                    $paymentMode,
                ),
                'payment_mode_workbook' => $paymentMode,
                'purpose' => $this->normalizeText($loanRequest->loan_purpose),
                'approved_date' => $approvedAt->format('F d, Y'),
                'approved_date_short' => $approvedAt->format('m/d/Y'),
                'maturity_date_short' => $maturityDate?->format('m/d/Y'),
                'term_days' => $approvedTerm !== null ? $approvedTerm * 30 : null,
                'insurance_term' => $approvedTerm !== null ? min($approvedTerm, 12) : null,
                'interest_rate_raw' => null,
                'service_charge_rate_raw' => null,
                'insurance_rate_raw' => null,
                'interest_rate_words' => null,
            ],
            'applicant' => $this->personDocumentData($applicant, $loanRequest),
            'co_maker_one' => $this->personDocumentData($coMakerOne, $loanRequest),
            'co_maker_two' => $this->personDocumentData($coMakerTwo, $loanRequest),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function personDocumentData(
        ?LoanRequestPerson $person,
        LoanRequest $loanRequest,
    ): array {
        return [
            'full_name' => $this->personFullName($person),
            'first_name' => $this->normalizeText($person?->first_name),
            'middle_name' => $this->normalizeText($person?->middle_name),
            'last_name' => $this->normalizeText($person?->last_name),
            'birthdate' => $this->formatBirthdate($person),
            'age' => $this->formatAge($person),
            'civil_status' => $this->normalizeText($person?->civil_status),
            'nationality' => 'FILIPINO',
            'address' => $this->normalizeText($person?->composedAddress()),
            'mobile' => $this->normalizeText($person?->cell_no),
            'email' => $this->normalizeText($loanRequest->user?->email),
            'employer_or_business' => $this->normalizeText($person?->employer_business_name),
            'position_or_designation' => $this->normalizeText($person?->current_position),
            'payday' => $this->normalizePaydayValue($person?->payday),
            'signature_path' => $this->normalizeText($person?->signature_path),
        ];
    }

    private function normalizeNumericValue(float|int|string|null $value): float|int|null
    {
        if ($value === null || ! is_numeric((string) $value)) {
            return null;
        }

        return (float) $value;
    }

    private function normalizeIntegerValue(float|int|string|null $value): ?int
    {
        if ($value === null || ! is_numeric((string) $value)) {
            return null;
        }

        return (int) round((float) $value);
    }

    private function formatCurrencyValue(float|int|string|null $value): ?string
    {
        if ($value === null || ! is_numeric((string) $value)) {
            return null;
        }

        return number_format((float) $value, 2, '.', ',');
    }

    private function formatBirthdate(?LoanRequestPerson $person): ?string
    {
        if ($person === null || $person->birthdate === null) {
            return null;
        }

        if ($person->birthdate instanceof Carbon) {
            return $person->birthdate->format('F d, Y');
        }

        $date = trim((string) $person->birthdate);

        if ($date === '') {
            return null;
        }

        try {
            return Carbon::parse($date)->format('F d, Y');
        } catch (Throwable) {
            return $date;
        }
    }

    private function formatAge(?LoanRequestPerson $person): ?string
    {
        if ($person === null || $person->birthdate === null) {
            return null;
        }

        try {
            $birthdate = $person->birthdate instanceof Carbon
                ? $person->birthdate
                : Carbon::parse((string) $person->birthdate);

            return (string) $birthdate->age;
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function normalizePaydayValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        if (in_array($trimmed, ['Weekly', '15th', '30th', '15th & 30th', 'Bi-Weekly', 'Monthly'], true)) {
            return $trimmed;
        }

        $upper = strtoupper($trimmed);
        $compact = preg_replace('/[^0-9A-Z]/', '', $upper) ?? '';

        return match (true) {
            $upper === 'WEEKLY' => 'Weekly',
            $upper === 'MONTHLY' => 'Monthly',
            $compact === 'BIWEEKLY' => 'Bi-Weekly',
            $compact === '15' => '15th',
            $compact === '30' => '30th',
            str_contains($upper, '15') && str_contains($upper, '30') => '15th & 30th',
            default => null,
        };
    }

    private function resolveWorkbookPaymentMode(?LoanRequestPerson $applicant): ?string
    {
        $payday = $this->normalizePaydayValue($applicant?->payday);

        return match ($payday) {
            'Weekly' => 'WEEKLY',
            'Bi-Weekly' => 'BI-WEEKLY',
            '15th & 30th' => 'SEMI-MONTHLY',
            '15th',
            '30th',
            'Monthly' => 'MONTHLY',
            default => null,
        };
    }

    private function resolveAmortizationCount(
        ?int $approvedTerm,
        ?string $paymentMode,
    ): ?int {
        if ($approvedTerm === null || $approvedTerm <= 0) {
            return null;
        }

        return match ($paymentMode) {
            'WEEKLY' => max(1, (int) round(($approvedTerm * 30) / 7)),
            'BI-WEEKLY' => max(1, (int) round(($approvedTerm * 30) / 14)),
            'SEMI-MONTHLY' => $approvedTerm * 2,
            default => $approvedTerm,
        };
    }

    private function resolveMaturityDate(
        CarbonInterface $approvedAt,
        ?int $approvedTerm,
    ): ?CarbonInterface {
        if ($approvedTerm === null || $approvedTerm <= 0) {
            return null;
        }

        return $approvedAt->copy()->addMonthsNoOverflow($approvedTerm);
    }

    private function formatCurrencyWords(float|int|string|null $value): ?string
    {
        if ($value === null || ! is_numeric((string) $value)) {
            return null;
        }

        if (! class_exists(NumberFormatter::class)) {
            return null;
        }

        try {
            $amount = round((float) $value, 2);
            $wholeNumber = (int) floor($amount);
            $decimalPart = (int) round(($amount - $wholeNumber) * 100);
            $formatter = new NumberFormatter('en', NumberFormatter::SPELLOUT);
            $formatted = $formatter->format($wholeNumber);

            if (! is_string($formatted) || trim($formatted) === '') {
                return null;
            }

            $words = strtoupper(str_replace('-', ' ', $formatted));
            $words = preg_replace('/\s+/', ' ', $words);
            $words = trim((string) $words);

            if ($decimalPart > 0) {
                return sprintf(
                    '%s PESOS AND %02d/100 ONLY.',
                    $words,
                    $decimalPart,
                );
            }

            return $words.' PESOS ONLY.';
        } catch (Throwable) {
            return null;
        }
    }

    private function personFullName(?LoanRequestPerson $person): ?string
    {
        if ($person === null) {
            return null;
        }

        $parts = array_filter([
            trim((string) $person->first_name),
            trim((string) $person->middle_name),
            trim((string) $person->last_name),
        ], static fn (string $value): bool => $value !== '');

        if ($parts === []) {
            return null;
        }

        return implode(' ', $parts);
    }

    private function resolvePerson(
        LoanRequest $loanRequest,
        LoanRequestPersonRole $role,
    ): ?LoanRequestPerson {
        if (! $loanRequest->relationLoaded('people')) {
            $loanRequest->loadMissing('people');
        }

        return $loanRequest->people
            ->first(function (LoanRequestPerson $person) use ($role): bool {
                $personRole = $person->role instanceof LoanRequestPersonRole
                    ? $person->role->value
                    : (string) $person->role;

                return $personRole === $role->value;
            });
    }

    private function normalizeReferenceForFilename(string $reference): string
    {
        $normalized = preg_replace('/[^A-Za-z0-9._-]/', '-', $reference);
        $normalized = trim((string) $normalized, '-');

        return $normalized !== '' ? $normalized : 'loan-request';
    }

    /**
     * @param  list<string>  $documentPaths
     */
    private function createZipArchive(string $zipPath, array $documentPaths): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException(
                'The ZIP extension is required to generate loan document packages.',
            );
        }

        $zip = new ZipArchive;
        $opened = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($opened !== true) {
            throw new RuntimeException('Unable to create ZIP archive.');
        }

        foreach ($documentPaths as $documentPath) {
            if (! is_file($documentPath)) {
                $zip->close();
                throw new RuntimeException('Missing generated document: '.$documentPath);
            }

            $zip->addFile($documentPath, basename($documentPath));
        }

        $zip->close();
    }
}

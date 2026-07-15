<?php

namespace App\Services\LoanRequests;

use App\LoanRequestDocumentKey;
use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\Models\LoanRequest;
use App\Models\LoanRequestPerson;
use App\Models\Wmaster;
use App\Services\LoanRequests\PdfFieldMaps\AffidavitUndertakingPdfFieldMap;
use App\Services\LoanRequests\PdfFieldMaps\AuthorizationPdfFieldMap;
use App\Services\LoanRequests\PdfFieldMaps\GrepalifePdfFieldMap;
use App\Services\LoanRequests\PdfFieldMaps\UndertakingBarangayPdfFieldMap;
use App\Services\OrganizationSettingsService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use NumberFormatter;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use ZipArchive;

class ApprovedLoanDocumentService
{
    private const GREPALIFE_IMAGE_TEMPLATE_PAGES = [
        [
            'image' => 'grepalife-page-1.png',
            'width' => 216.0,
            'height' => 279.0,
        ],
        [
            'image' => 'grepalife-page-2.png',
            'width' => 216.0,
            'height' => 279.0,
        ],
    ];

    private const PDF_TEMPLATE_FILENAMES = [
        'grepalife' => 'grepalife.pdf',
        'undertaking_barangay' => 'undertaking-barangay-officials.pdf',
        'affidavit_undertaking' => 'affidavit-undertaking.pdf',
        'authorization' => 'authorization.pdf',
    ];

    /**
     * @var array<string, string>
     */
    private const ZIP_DOCUMENT_NAMES = [
        'application_form' => '01-Application-Form.pdf',
        'grepalife' => '02-GREPALIFE.pdf',
        'affidavit_undertaking' => '03-Affidavit-of-Undertaking.pdf',
        'authorization' => '04-Authorization.pdf',
        'loan_information' => '05-Loan-Information.pdf',
        'plan_of_payment' => '06-Plan-of-Payment.pdf',
        'disclosure_statement' => '07-Disclosure-Statement.pdf',
        'promissory_note' => '08-Promissory-Note.pdf',
        'undertaking_barangay' => '09-Undertaking-Barangay-Officials.pdf',
        'loan_security_agreement' => '10-Loan-Security-Agreement.pdf',
    ];

    /**
     * @var array<string, string>
     */
    private const DOWNLOAD_DOCUMENT_NAMES = [
        'application_form' => 'application-form-%s.pdf',
        'grepalife' => 'grepalife-%s.pdf',
        'affidavit_undertaking' => 'affidavit-undertaking-%s.pdf',
        'authorization' => 'authorization-%s.pdf',
        'loan_information' => 'loan-information-%s.pdf',
        'plan_of_payment' => 'plan-of-payment-%s.pdf',
        'disclosure_statement' => 'disclosure-statement-%s.pdf',
        'promissory_note' => 'promissory-note-%s.pdf',
        'undertaking_barangay' => 'undertaking-barangay-%s.pdf',
        'loan_security_agreement' => '%s Loan Request Agreement.pdf',
    ];

    public function __construct(
        private LoanRequestPdfService $loanRequestPdfService,
        private OrganizationSettingsService $organizationSettingsService,
        private OfficialLoanManagerResolver $officialLoanManagerResolver,
        private LoanSecurityAgreementPdfService $loanSecurityAgreementPdfService,
        private ApprovedLoanImageTemplatePdfService $approvedLoanImageTemplatePdfService,
        private ApprovedLoanPdfTemplateService $approvedLoanPdfTemplateService,
        private LoanRequestDataService $loanRequestDataService,
        private LoanRequestDocumentCatalog $documentCatalog,
        private LoanRequestDocumentStorage $documentStorage,
        private GrepalifePdfFieldMap $grepalifePdfFieldMap,
        private UndertakingBarangayPdfFieldMap $undertakingBarangayPdfFieldMap,
        private AffidavitUndertakingPdfFieldMap $affidavitUndertakingPdfFieldMap,
        private AuthorizationPdfFieldMap $authorizationPdfFieldMap,
        private PromissoryNotePdfService $promissoryNotePdfService,
        private PlanOfPaymentPdfService $planOfPaymentPdfService,
        private LoanInformationPdfService $loanInformationPdfService,
        private DisclosureStatementPdfService $disclosureStatementPdfService,
    ) {}

    public function applicationForm(LoanRequest $loanRequest): Response
    {
        $loanRequest->loadMissing(
            'people',
            'reviewedBy.adminProfile',
            'reviewedBy.activeAdminSignature',
            'approvalSignature',
            'user',
        );

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
        $loanRequest->loadMissing(
            'people',
            'reviewedBy.adminProfile',
            'reviewedBy.activeAdminSignature',
            'approvalSignature',
            'user',
        );

        return $this->approvedLoanImageTemplatePdfService->renderResponse(
            self::GREPALIFE_IMAGE_TEMPLATE_PAGES,
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
                $this->loanSecurityAgreementPdfService->generate(
                    $outputPath,
                    $documentData,
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
            'application/pdf',
            function (string $outputPath, array $documentData): void {
                $this->planOfPaymentPdfService->generate($outputPath, $documentData);
            },
        );
    }

    public function loanInformation(LoanRequest $loanRequest): Response
    {
        return $this->downloadApprovedDocument(
            $loanRequest,
            'loan_information',
            'application/pdf',
            function (string $outputPath, array $documentData): void {
                $this->loanInformationPdfService->generate($outputPath, $documentData);
            },
        );
    }

    public function disclosureStatement(LoanRequest $loanRequest): Response
    {
        return $this->downloadApprovedDocument(
            $loanRequest,
            'disclosure_statement',
            'application/pdf',
            function (string $outputPath, array $documentData): void {
                $this->disclosureStatementPdfService->generate($outputPath, $documentData);
            },
        );
    }

    public function promissoryNote(LoanRequest $loanRequest): Response
    {
        return $this->downloadApprovedDocument(
            $loanRequest,
            'promissory_note',
            'application/pdf',
            function (string $outputPath, array $documentData): void {
                $this->promissoryNotePdfService->generate($outputPath, $documentData);
            },
        );
    }

    public function packageZip(LoanRequest $loanRequest): Response
    {
        $this->ensureApproved($loanRequest);
        $loanRequest->loadMissing(
            'people',
            'reviewedBy.adminProfile',
            'reviewedBy.activeAdminSignature',
            'approvalSignature',
            'user',
        );

        $workingDirectory = $this->makeWorkingDirectory($loanRequest);
        $documentDirectory = $workingDirectory.DIRECTORY_SEPARATOR.'documents';
        File::ensureDirectoryExists($documentDirectory);

        try {
            $documentData = $this->buildDocumentData($loanRequest);

            $applicationFormPath = $documentDirectory.DIRECTORY_SEPARATOR.self::ZIP_DOCUMENT_NAMES['application_form'];
            $grepalifePath = $documentDirectory.DIRECTORY_SEPARATOR.self::ZIP_DOCUMENT_NAMES['grepalife'];
            $affidavitUndertakingPath = $documentDirectory.DIRECTORY_SEPARATOR.self::ZIP_DOCUMENT_NAMES['affidavit_undertaking'];
            $authorizationPath = $documentDirectory.DIRECTORY_SEPARATOR.self::ZIP_DOCUMENT_NAMES['authorization'];
            $loanInformationPath = $documentDirectory.DIRECTORY_SEPARATOR.self::ZIP_DOCUMENT_NAMES['loan_information'];
            $planOfPaymentPath = $documentDirectory.DIRECTORY_SEPARATOR.self::ZIP_DOCUMENT_NAMES['plan_of_payment'];
            $disclosureStatementPath = $documentDirectory.DIRECTORY_SEPARATOR.self::ZIP_DOCUMENT_NAMES['disclosure_statement'];
            $promissoryNotePath = $documentDirectory.DIRECTORY_SEPARATOR.self::ZIP_DOCUMENT_NAMES['promissory_note'];
            $undertakingBarangayPath = $documentDirectory.DIRECTORY_SEPARATOR.self::ZIP_DOCUMENT_NAMES['undertaking_barangay'];
            $loanSecurityAgreementPath = $documentDirectory.DIRECTORY_SEPARATOR.self::ZIP_DOCUMENT_NAMES['loan_security_agreement'];

            $this->loanRequestPdfService->saveToPath($loanRequest, $applicationFormPath);
            $this->approvedLoanImageTemplatePdfService->generate(
                self::GREPALIFE_IMAGE_TEMPLATE_PAGES,
                $grepalifePath,
                $documentData,
                $this->grepalifePdfFieldMap,
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
            $this->loanInformationPdfService->generate(
                $loanInformationPath,
                $documentData,
            );
            $this->planOfPaymentPdfService->generate(
                $planOfPaymentPath,
                $documentData,
            );
            $this->disclosureStatementPdfService->generate(
                $disclosureStatementPath,
                $documentData,
            );
            $this->promissoryNotePdfService->generate(
                $promissoryNotePath,
                $documentData,
            );
            $this->approvedLoanPdfTemplateService->generate(
                self::PDF_TEMPLATE_FILENAMES['undertaking_barangay'],
                $undertakingBarangayPath,
                $documentData,
                $this->undertakingBarangayPdfFieldMap,
            );
            $this->loanSecurityAgreementPdfService->generate(
                $loanSecurityAgreementPath,
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
                $affidavitUndertakingPath,
                $authorizationPath,
                $loanInformationPath,
                $planOfPaymentPath,
                $disclosureStatementPath,
                $promissoryNotePath,
                $undertakingBarangayPath,
                $loanSecurityAgreementPath,
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
     * @return array{mime_type:string, filename:string, sheet_name:string|null}
     */
    public function generateToPathForKey(
        LoanRequest $loanRequest,
        LoanRequestDocumentKey $documentKey,
        string $outputPath,
        ?array $documentData = null,
    ): array {
        $documentData ??= $this->buildDocumentData($loanRequest);

        File::ensureDirectoryExists(dirname($outputPath));

        return match ($documentKey) {
            LoanRequestDocumentKey::ApplicationForm => $this->generateApplicationFormToPath(
                $loanRequest,
                $outputPath,
            ),
            LoanRequestDocumentKey::Grepalife => $this->generatePdfDocumentToPath(
                $outputPath,
                $documentKey,
                function (string $path) use ($documentData): void {
                    $this->approvedLoanImageTemplatePdfService->generate(
                        self::GREPALIFE_IMAGE_TEMPLATE_PAGES,
                        $path,
                        $documentData,
                        $this->grepalifePdfFieldMap,
                    );
                },
            ),
            LoanRequestDocumentKey::LoanSecurityAgreement => $this->generatePdfDocumentToPath(
                $outputPath,
                $documentKey,
                function (string $path) use ($documentData): void {
                    $this->loanSecurityAgreementPdfService->generate(
                        $path,
                        $documentData,
                    );
                },
            ),
            LoanRequestDocumentKey::UndertakingBarangay => $this->generatePdfDocumentToPath(
                $outputPath,
                $documentKey,
                function (string $path) use ($documentData): void {
                    $this->approvedLoanPdfTemplateService->generate(
                        self::PDF_TEMPLATE_FILENAMES['undertaking_barangay'],
                        $path,
                        $documentData,
                        $this->undertakingBarangayPdfFieldMap,
                    );
                },
            ),
            LoanRequestDocumentKey::AffidavitUndertaking => $this->generatePdfDocumentToPath(
                $outputPath,
                $documentKey,
                function (string $path) use ($documentData): void {
                    $this->approvedLoanPdfTemplateService->generate(
                        self::PDF_TEMPLATE_FILENAMES['affidavit_undertaking'],
                        $path,
                        $documentData,
                        $this->affidavitUndertakingPdfFieldMap,
                    );
                },
            ),
            LoanRequestDocumentKey::Authorization => $this->generatePdfDocumentToPath(
                $outputPath,
                $documentKey,
                function (string $path) use ($documentData): void {
                    $this->approvedLoanPdfTemplateService->generate(
                        self::PDF_TEMPLATE_FILENAMES['authorization'],
                        $path,
                        $documentData,
                        $this->authorizationPdfFieldMap,
                    );
                },
            ),
            LoanRequestDocumentKey::PromissoryNote => $this->generatePdfDocumentToPath(
                $outputPath,
                $documentKey,
                function (string $path) use ($documentData): void {
                    $this->promissoryNotePdfService->generate($path, $documentData);
                },
            ),
            LoanRequestDocumentKey::PlanOfPayment => $this->generatePdfDocumentToPath(
                $outputPath,
                $documentKey,
                function (string $path) use ($documentData): void {
                    $this->planOfPaymentPdfService->generate($path, $documentData);
                },
            ),
            LoanRequestDocumentKey::LoanInformation => $this->generatePdfDocumentToPath(
                $outputPath,
                $documentKey,
                function (string $path) use ($documentData): void {
                    $this->loanInformationPdfService->generate($path, $documentData);
                },
            ),
            LoanRequestDocumentKey::DisclosureStatement => $this->generatePdfDocumentToPath(
                $outputPath,
                $documentKey,
                function (string $path) use ($documentData): void {
                    $this->disclosureStatementPdfService->generate($path, $documentData);
                },
            ),
        };
    }

    public function templateVersionFor(LoanRequestDocumentKey $documentKey): string
    {
        return $this->documentCatalog->templateVersionFor($documentKey);
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
        $loanRequest->loadMissing('people', 'reviewedBy.adminProfile', 'user');

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

    /**
     * @return array{mime_type:string, filename:string, sheet_name:string|null}
     */
    private function generateApplicationFormToPath(
        LoanRequest $loanRequest,
        string $outputPath,
    ): array {
        $loanRequest->loadMissing(
            'people',
            'reviewedBy.adminProfile',
            'reviewedBy.activeAdminSignature',
            'approvalSignature',
            'user',
        );

        $this->loanRequestPdfService->saveToPath($loanRequest, $outputPath);

        return [
            'mime_type' => 'application/pdf',
            'filename' => basename($outputPath),
            'sheet_name' => null,
        ];
    }

    /**
     * @param  callable(string): void  $generator
     * @return array{mime_type:string, filename:string, sheet_name:string|null}
     */
    private function generatePdfDocumentToPath(
        string $outputPath,
        LoanRequestDocumentKey $documentKey,
        callable $generator,
    ): array {
        $generator($outputPath);

        return [
            'mime_type' => 'application/pdf',
            'filename' => basename($outputPath),
            'sheet_name' => null,
        ];
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
        return $this->documentStorage->temporaryWorkingDirectory(
            'approved-loan-documents',
            $loanRequest->id,
        );
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function buildDocumentData(
        LoanRequest $loanRequest,
        array $overrides = [],
        bool $allowDefaultFinancialValues = false,
    ): array {
        $loanRequest->loadMissing(
            'people',
            'assignedProcessor.adminProfile',
            'reviewedBy.adminProfile',
            'reviewedBy.activeAdminSignature',
            'approvedBy.adminProfile',
            'approvalSignature',
            'user',
        );

        $applicant = $this->resolvePerson($loanRequest, LoanRequestPersonRole::Applicant);
        $coMakerOne = $this->resolvePerson($loanRequest, LoanRequestPersonRole::CoMakerOne);
        $coMakerTwo = $this->resolvePerson($loanRequest, LoanRequestPersonRole::CoMakerTwo);
        $memberRecord = $this->resolveMemberWmaster($loanRequest);
        $flatValues = $this->loanRequestDataService->loadFlatValues($loanRequest);
        $branding = $this->organizationSettingsService->branding();
        $documentDate = $this->resolveDocumentDate($loanRequest);
        $approvedTerm = $this->normalizeIntegerValue($loanRequest->approved_term);
        $approvedAmountRaw = $this->normalizeNumericValue($loanRequest->approved_amount);
        $paymentMode = $this->resolveWorkbookPaymentModeValue(
            $this->normalizeText(
                is_array($overrides['loan'] ?? null)
                    ? ($overrides['loan']['payment_mode_workbook'] ?? null)
                    : null,
            ) ?? $this->normalizeText($loanRequest->recommended_payment_frequency),
        );
        $officialLoanManager = $this->officialLoanManagerResolver->documentData();
        $overrideLoan = is_array($overrides['loan'] ?? null)
            ? $overrides['loan']
            : [];
        $overrideReviewer = is_array($overrides['reviewer'] ?? null)
            ? $overrides['reviewer']
            : [];
        $overrideProcessing = is_array($overrides['processing'] ?? null)
            ? $overrides['processing']
            : [];
        $approverDisplayName = $loanRequest->approvedBy?->adminProfile?->fullname
            ?? $loanRequest->approvedBy?->name
            ?? $loanRequest->approvedBy?->username;
        $approverName = $this->normalizeText($approverDisplayName);
        $reviewerName = $this->normalizeText($overrideReviewer['name'] ?? null)
            ?? $approverName
            ?? $this->normalizeText($officialLoanManager['name']);
        $reviewerPosition = $this->normalizeText($overrideReviewer['position'] ?? null)
            ?? ($approverName !== null ? $this->officialLoanManagerResolver->position() : null)
            ?? $this->normalizeText($officialLoanManager['position']);
        $processorName = $loanRequest->assignedProcessor?->adminProfile?->fullname
            ?? $loanRequest->assignedProcessor?->name
            ?? $loanRequest->assignedProcessor?->username;
        $processorDisplayName = $this->normalizeText($processorName);
        $amortizationCount = $this->resolveAmortizationCount(
            $approvedTerm,
            $paymentMode,
        );
        $maturityDate = $this->resolveMaturityDate($documentDate, $approvedTerm);
        $interestRateRaw = $this->resolveNumericOverride(
            $overrideLoan['interest_rate_raw'] ?? null,
            $this->normalizeNumericValue($loanRequest->approved_interest_rate),
        );
        $serviceChargeRateRaw = $this->resolveNumericOverride(
            $overrideLoan['service_charge_rate_raw']
                ?? $flatValues['service_charge_rate']
                ?? null,
            null,
        );
        $insuranceRateRaw = $this->resolveNumericOverride(
            $overrideLoan['insurance_rate_raw']
                ?? $flatValues['insurance_rate']
                ?? null,
            0.0,
        );
        $insuranceTerm = $this->resolveIntegerOverride(
            $overrideLoan['insurance_term']
                ?? $flatValues['insurance_term']
                ?? null,
            0,
        );
        $interestNotDeductedRaw = $this->roundCurrency(
            $approvedAmountRaw !== null && $approvedTerm !== null && $interestRateRaw !== null
                ? ($approvedAmountRaw * $interestRateRaw / 12) * $approvedTerm
                : null,
        );
        $serviceChargeAmountRaw = $this->roundCurrency(
            $approvedAmountRaw !== null && $serviceChargeRateRaw !== null
                ? $approvedAmountRaw * $serviceChargeRateRaw
                : null,
        );
        $insurancePremiumRaw = $this->roundCurrency(
            $approvedAmountRaw !== null && $insuranceTerm !== null && $insuranceRateRaw !== null
                ? ($approvedAmountRaw / 1000) * $insuranceTerm * $insuranceRateRaw
                : null,
        );
        $loanSecurityRateRaw = $this->resolveNumericOverride(
            $overrideLoan['loan_security_rate_raw']
                ?? $flatValues['loan_security_rate']
                ?? null,
            0.0,
        );
        $loanSecurityAmountRaw = $this->roundCurrency(
            $approvedAmountRaw !== null && $loanSecurityRateRaw !== null
                ? $approvedAmountRaw * $loanSecurityRateRaw
                : null,
        );
        $documentaryStampRateRaw = $this->resolveNumericOverride(
            $overrideLoan['documentary_stamp_rate_raw']
                ?? $flatValues['documentary_stamp_rate']
                ?? null,
            null,
        );
        $documentaryStampAmountRaw = $this->roundCurrency(
            $approvedAmountRaw !== null && $documentaryStampRateRaw !== null
                ? $approvedAmountRaw * $documentaryStampRateRaw
                : null,
        );
        $notarialFeeRaw = $this->resolveNumericOverride(
            $overrideLoan['notarial_fee_raw']
                ?? $flatValues['notarial_fee']
                ?? null,
            null,
        );
        $principalAmortizationRaw = $this->roundCurrency(
            $approvedAmountRaw !== null && $amortizationCount !== null && $amortizationCount > 0
                ? $approvedAmountRaw / $amortizationCount
                : null,
        );
        $interestAmortizationRaw = $this->roundCurrency(
            $interestNotDeductedRaw !== null && $amortizationCount !== null && $amortizationCount > 0
                ? $interestNotDeductedRaw / $amortizationCount
                : null,
        );
        $loanSecurityAmortizationRaw = $this->roundCurrency(
            $principalAmortizationRaw !== null && $loanSecurityRateRaw !== null
                ? $principalAmortizationRaw * $loanSecurityRateRaw
                : null,
        );
        $amortizationTotalRaw = $this->roundCurrency(
            $this->sumAmounts(
                $principalAmortizationRaw,
                $interestAmortizationRaw,
                $loanSecurityAmortizationRaw,
            ),
        );
        $financeChargeTotalRaw = $this->roundCurrency(
            $this->sumAmounts($interestNotDeductedRaw, $serviceChargeAmountRaw),
        );
        $nonFinanceChargeTotalRaw = $this->roundCurrency(
            $this->sumAmounts(
                $insurancePremiumRaw,
                $loanSecurityAmountRaw,
                $documentaryStampAmountRaw,
                $notarialFeeRaw,
            ),
        );
        $deductionsTotalRaw = $this->roundCurrency(
            $this->sumAmounts($financeChargeTotalRaw, $nonFinanceChargeTotalRaw),
        );
        $netProceedsRaw = $this->roundCurrency(
            $approvedAmountRaw !== null && $deductionsTotalRaw !== null
                ? $approvedAmountRaw - $deductionsTotalRaw
                : null,
        );
        $penaltyRateRaw = $this->resolveNumericOverride(
            $overrideLoan['penalty_rate_raw']
                ?? $flatValues['penalty_rate_per_month']
                ?? null,
            null,
        );
        $witnessOneName = $this->normalizeText(
            $overrideReviewer['witness_one_name'] ?? null,
        ) ?? $this->normalizeText($flatValues['witness_one_name'] ?? null);
        $witnessTwoName = $this->normalizeText(
            $overrideReviewer['witness_two_name'] ?? null,
        ) ?? $this->normalizeText($flatValues['witness_two_name'] ?? null);

        $documentData = [
            'organization' => [
                'company_name' => $this->normalizeText($branding['companyName'] ?? null),
                'business_address' => $this->normalizeText(
                    $branding['businessAddress'] ?? null,
                ),
                'business_address1' => $this->normalizeText(
                    $branding['businessAddress1'] ?? null,
                ),
                'business_address2' => $this->normalizeText(
                    $branding['businessAddress2'] ?? null,
                ),
                'business_address3' => $this->normalizeText(
                    $branding['businessAddress3'] ?? null,
                ),
                'support_contact_name' => $this->normalizeText(
                    $branding['supportContactName'] ?? null,
                ),
                'logo_data_uri' => $this->organizationSettingsService->logoDataUri(),
                'report_header' => is_array($branding['reportHeader'] ?? null)
                    ? $branding['reportHeader']
                    : [],
                'report_typography' => is_array(
                    $branding['reportTypography'] ?? null,
                )
                    ? $branding['reportTypography']
                    : [],
            ],
            'processor' => [
                'name' => $processorDisplayName,
                'position' => $processorDisplayName !== null
                    ? 'Loan Processor'
                    : null,
            ],
            'reviewer' => [
                'name' => $reviewerName,
                'position' => $reviewerPosition,
                'signature_path' => null,
                'signature_data' => null,
                'witness_one_name' => $witnessOneName,
                'witness_two_name' => $witnessTwoName,
            ],
            'loan' => [
                'reference' => $loanRequest->reference,
                'type' => $this->normalizeText($loanRequest->loan_type_label_snapshot)
                    ?? $this->normalizeText($loanRequest->typecode),
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
                'amortization_count' => $amortizationCount,
                'payment_mode_workbook' => $paymentMode,
                'purpose' => $this->normalizeText($loanRequest->loan_purpose),
                'approved_date' => $documentDate?->format('F d, Y'),
                'approved_date_short' => $documentDate?->format('m/d/Y'),
                'maturity_date_short' => $maturityDate?->format('m/d/Y'),
                'term_days' => $approvedTerm !== null ? $approvedTerm * 30 : null,
                'recommended_by' => $processorDisplayName,
                'recommendation_remarks' => $this->normalizeText(
                    $loanRequest->recommendation_remarks,
                ),
                'insurance_term' => $insuranceTerm,
                'interest_rate_raw' => $interestRateRaw,
                'service_charge_rate_raw' => $serviceChargeRateRaw,
                'insurance_rate_raw' => $insuranceRateRaw,
                'loan_security_rate_raw' => $loanSecurityRateRaw,
                'documentary_stamp_rate_raw' => $documentaryStampRateRaw,
                'interest_rate_words' => $this->formatPercentWords($interestRateRaw),
                'interest_not_deducted_raw' => $interestNotDeductedRaw,
                'service_charge_amount_raw' => $serviceChargeAmountRaw,
                'insurance_premium_raw' => $insurancePremiumRaw,
                'loan_security_amount_raw' => $loanSecurityAmountRaw,
                'documentary_stamp_amount_raw' => $documentaryStampAmountRaw,
                'notarial_fee_raw' => $notarialFeeRaw,
                'finance_charge_total_raw' => $financeChargeTotalRaw,
                'non_finance_charge_total_raw' => $nonFinanceChargeTotalRaw,
                'deductions_total_raw' => $deductionsTotalRaw,
                'net_proceeds_raw' => $netProceedsRaw,
                'amortization_principal_raw' => $principalAmortizationRaw,
                'amortization_interest_raw' => $interestAmortizationRaw,
                'amortization_loan_security_raw' => $loanSecurityAmortizationRaw,
                'amortization_total_raw' => $amortizationTotalRaw,
                'penalty_rate_raw' => $penaltyRateRaw,
                'gnthp_raw' => $this->normalizeNumericValue(
                    $overrideProcessing['guaranteed_net_take_home_pay'] ?? $flatValues['guaranteed_net_take_home_pay'] ?? null,
                ),
                'gnthp' => $this->formatCurrencyValue(
                    $this->normalizeNumericValue(
                        $overrideProcessing['guaranteed_net_take_home_pay'] ?? $flatValues['guaranteed_net_take_home_pay'] ?? null,
                    ),
                ),
            ],
            'authorization' => [
                'release_method' => $this->normalizeText(
                    $overrideProcessing['release_method'] ?? $flatValues['release_method'] ?? null,
                ),
                'payout_bank_name' => $this->normalizeText(
                    $overrideProcessing['payout_bank_name'] ?? $flatValues['payout_bank_name'] ?? null,
                ),
                'payout_account_name' => $this->normalizeText(
                    $overrideProcessing['payout_account_name'] ?? $flatValues['payout_account_name'] ?? null,
                ),
                'payout_account_number' => $this->normalizeText(
                    $overrideProcessing['payout_account_number'] ?? $flatValues['payout_account_number'] ?? null,
                ),
                'payout_account_type' => $this->normalizeText(
                    $overrideProcessing['payout_account_type'] ?? $flatValues['payout_account_type'] ?? null,
                ),
                'payout_atm_number' => $this->normalizeText(
                    $overrideProcessing['payout_atm_number'] ?? $flatValues['payout_atm_number'] ?? null,
                ),
                'payout_bank_branch' => $this->normalizeText(
                    $overrideProcessing['payout_bank_branch'] ?? $flatValues['payout_bank_branch'] ?? null,
                ),
                'payout_atm_holder_name' => $this->normalizeText(
                    $overrideProcessing['payout_atm_holder_name'] ?? $flatValues['payout_atm_holder_name'] ?? null,
                ),
            ],
            'barangay' => [
                'name' => $this->normalizeText(
                    $overrideProcessing['barangay_name'] ?? $flatValues['barangay_name'] ?? null,
                ),
                'clearance_reference' => $this->normalizeText(
                    $overrideProcessing['barangay_clearance_reference'] ?? $flatValues['barangay_clearance_reference'] ?? null,
                ),
                'locality' => $this->normalizeText(
                    $overrideProcessing['barangay_locality'] ?? $flatValues['barangay_locality'] ?? null,
                ),
                'official_name' => $this->normalizeText(
                    $overrideProcessing['barangay_official_name'] ?? $flatValues['barangay_official_name'] ?? null,
                ),
                'official_title' => $this->normalizeText(
                    $overrideProcessing['barangay_official_title'] ?? $flatValues['barangay_official_title'] ?? null,
                ),
                'official_designation' => $this->normalizeText(
                    $overrideProcessing['barangay_official_designation'] ?? $flatValues['barangay_official_designation'] ?? null,
                ),
                'agency_name' => $this->normalizeText(
                    $overrideProcessing['barangay_agency_name'] ?? $flatValues['barangay_agency_name'] ?? null,
                ),
                'agency_address' => $this->normalizeText(
                    $overrideProcessing['barangay_agency_address'] ?? $flatValues['barangay_agency_address'] ?? null,
                ),
            ],
            'security' => [
                'notarial_venue' => $this->normalizeText(
                    $overrideProcessing['notarial_venue'] ?? $flatValues['notarial_venue'] ?? null,
                ),
            ],
            'notarial' => [
                // Place of signing, notarial province, and ID-issuance location are the
                // notary's own fixed office facts, not per-loan staff input — they resolve
                // to the org's configured business address (OrganizationSettingsService).
                'signing_place' => $this->normalizeText($branding['businessAddress2'] ?? null),
                'province' => $this->normalizeText($branding['businessAddress3'] ?? null),
                // Doc/Page/Book No., valid ID number, and ID-issuance location have no
                // reference-document equivalent on AU — left blank on the printed form
                // for the notary to fill by hand.
                'series_year' => $documentDate?->format('Y'),
            ],
            'health' => [
                'health_smoker' => $overrideProcessing['health_smoker'] ?? $flatValues['health_smoker'] ?? null,
                'health_hypertension' => $overrideProcessing['health_hypertension'] ?? $flatValues['health_hypertension'] ?? null,
                'health_diabetes' => $overrideProcessing['health_diabetes'] ?? $flatValues['health_diabetes'] ?? null,
                'health_recent_hospitalization' => $overrideProcessing['health_recent_hospitalization'] ?? $flatValues['health_recent_hospitalization'] ?? null,
                'health_declaration_notes' => $this->normalizeText(
                    $overrideProcessing['health_declaration_notes'] ?? $flatValues['health_declaration_notes'] ?? null,
                ),
            ],
            'applicant' => $this->personDocumentData($applicant, $loanRequest),
            'co_maker_one' => $this->personDocumentData($coMakerOne, $loanRequest),
            'co_maker_two' => $this->personDocumentData($coMakerTwo, $loanRequest),
            'beneficiaries' => $this->beneficiaryDocumentData($flatValues, $memberRecord),
        ];

        return $overrides !== []
            ? array_replace_recursive($documentData, $overrides)
            : $documentData;
    }

    /**
     * @return array<string, mixed>
     */
    private function personDocumentData(
        ?LoanRequestPerson $person,
        LoanRequest $loanRequest,
    ): array {
        $composedBirthplace = $this->normalizeText($person?->composedBirthplace());
        $composedAddress = $this->normalizeText($person?->composedAddress());
        $composedOfficeAddress = $this->normalizeText(
            $person?->composedEmployerBusinessAddress(),
        );
        $addressLine = $this->normalizeText($person?->address1) ?? $composedAddress;
        $officeAddress = $this->normalizeText($person?->employer_business_address1)
            ?? $composedOfficeAddress;

        return [
            'full_name' => $this->personFullName($person),
            'first_name' => $this->normalizeText($person?->first_name),
            'middle_name' => $this->normalizeText($person?->middle_name),
            'last_name' => $this->normalizeText($person?->last_name),
            'birthdate' => $this->formatBirthdate($person),
            'age' => $this->formatAge($person),
            'civil_status' => $this->normalizeText($person?->civil_status),
            'nationality' => 'FILIPINO',
            'place_of_birth' => $composedBirthplace,
            'place_of_birth_city' => $this->normalizeText($person?->birthplace_city),
            'place_of_birth_province' => $this->normalizeText(
                $person?->birthplace_province,
            ),
            'address' => $composedAddress,
            'address_line' => $addressLine,
            'address_city' => $this->normalizeText($person?->address2),
            'address_province' => $this->normalizeText($person?->address3),
            'address_country' => null,
            'address_zip' => null,
            'contact_number' => $this->normalizeText($person?->cell_no),
            'mobile' => $this->normalizeText($person?->cell_no),
            'home_phone' => null,
            'work_phone' => $this->normalizeText($person?->telephone_no),
            'email' => $this->normalizeText($loanRequest->user?->email),
            'employer_or_business' => $this->normalizeText($person?->employer_business_name),
            'office_address' => $officeAddress,
            'office_city' => $this->normalizeText($person?->employer_business_address2),
            'office_province' => $this->normalizeText(
                $person?->employer_business_address3,
            ),
            'office_country' => null,
            'office_zip' => null,
            'position_or_designation' => $this->normalizeText($person?->current_position),
            'nature_of_business' => $this->normalizeText($person?->nature_of_business),
            'years_in_work_business' => $this->normalizeText(
                $person?->years_in_work_business,
            ),
            'payday' => $this->normalizePaydayValue($person?->payday),
            'signature_path' => null,
        ];
    }

    private function resolveMemberWmaster(LoanRequest $loanRequest): ?Wmaster
    {
        if (! Schema::hasTable('wmaster')) {
            return null;
        }

        $loanRequest->loadMissing('user');

        $user = $loanRequest->user;

        if ($user !== null) {
            $user->loadMissing('wmaster');

            if ($user->wmaster instanceof Wmaster) {
                return $user->wmaster;
            }
        }

        $acctno = trim((string) ($loanRequest->acctno ?? $user?->acctno ?? ''));

        if ($acctno === '') {
            return null;
        }

        return Wmaster::query()->where('acctno', $acctno)->first();
    }

    /**
     * @param  array<string, mixed>  $flatValues
     * @return array<int, array{name: string, birthdate: string|null, relationship: string|null}>
     */
    private function beneficiaryDocumentData(array $flatValues, ?Wmaster $memberRecord): array
    {
        $beneficiariesFromEntries = array_values(array_filter([
            ! $this->isBlankString($flatValues['beneficiary_primary_name'] ?? null)
                ? [
                    'name' => $this->normalizeText((string) $flatValues['beneficiary_primary_name']),
                    'birthdate' => $this->formatShortDateValue(
                        $flatValues['beneficiary_primary_birthdate'] ?? null,
                    ),
                    'relationship' => $this->normalizeText(
                        is_scalar($flatValues['beneficiary_primary_relationship'] ?? null)
                            ? (string) $flatValues['beneficiary_primary_relationship']
                            : null,
                    ),
                ]
                : null,
            ! $this->isBlankString($flatValues['beneficiary_secondary_name'] ?? null)
                ? [
                    'name' => $this->normalizeText((string) $flatValues['beneficiary_secondary_name']),
                    'birthdate' => $this->formatShortDateValue(
                        $flatValues['beneficiary_secondary_birthdate'] ?? null,
                    ),
                    'relationship' => $this->normalizeText(
                        is_scalar($flatValues['beneficiary_secondary_relationship'] ?? null)
                            ? (string) $flatValues['beneficiary_secondary_relationship']
                            : null,
                    ),
                ]
                : null,
        ]));

        if ($beneficiariesFromEntries !== []) {
            return $beneficiariesFromEntries;
        }

        if (! $memberRecord instanceof Wmaster) {
            return [];
        }

        $linkedBeneficiaries = $this->resolveLinkedBeneficiaryMembers($memberRecord);
        $beneficiaries = [];

        for ($slot = 1; $slot <= 3; $slot++) {
            $directName = $this->normalizeText(
                $this->stringAttribute($memberRecord, 'beneficiary'.$slot),
            );

            if ($directName !== null) {
                $beneficiaries[] = [
                    'name' => $directName,
                    'birthdate' => $this->formatShortDateValue(
                        $memberRecord->getAttribute('ben'.$slot.'_bday'),
                    ),
                    'relationship' => null,
                ];

                continue;
            }

            $linkedAcctno = trim(
                (string) $memberRecord->getAttribute('ben'.$slot.'_acctno'),
            );

            if ($linkedAcctno === '') {
                continue;
            }

            $linkedBeneficiary = $linkedBeneficiaries[$linkedAcctno] ?? null;

            if (! $linkedBeneficiary instanceof Wmaster) {
                continue;
            }

            $linkedName = $this->normalizeText($linkedBeneficiary->displayName());

            if ($linkedName === null) {
                continue;
            }

            $beneficiaries[] = [
                'name' => $linkedName,
                'birthdate' => $this->formatShortDateValue($linkedBeneficiary->birthday),
                'relationship' => null,
            ];
        }

        return array_slice($beneficiaries, 0, 3);
    }

    /**
     * @return array<string, Wmaster>
     */
    private function resolveLinkedBeneficiaryMembers(Wmaster $memberRecord): array
    {
        $acctnos = [];

        for ($slot = 1; $slot <= 3; $slot++) {
            $acctno = trim((string) $memberRecord->getAttribute('ben'.$slot.'_acctno'));

            if ($acctno !== '') {
                $acctnos[] = $acctno;
            }
        }

        $acctnos = array_values(array_unique($acctnos));

        if ($acctnos === []) {
            return [];
        }

        return Wmaster::query()
            ->whereIn('acctno', $acctnos)
            ->get()
            ->keyBy(fn (Wmaster $beneficiary): string => trim((string) $beneficiary->acctno))
            ->all();
    }

    private function stringAttribute(Wmaster $memberRecord, string $attribute): ?string
    {
        $value = $memberRecord->getAttribute($attribute);

        return is_string($value) ? $value : null;
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

    private function resolveNumericOverride(
        mixed $value,
        float|int|null $default,
    ): float|int|null {
        $normalized = $this->normalizeNumericValue(
            is_array($value) ? null : $value,
        );

        return $normalized ?? $default;
    }

    private function resolveIntegerOverride(
        mixed $value,
        ?int $default,
    ): ?int {
        $normalized = $this->normalizeIntegerValue(
            is_array($value) ? null : $value,
        );

        return $normalized ?? $default;
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

    private function formatShortDateValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->format('m/d/Y');
        }

        $date = trim((string) $value);

        if ($date === '') {
            return null;
        }

        try {
            return Carbon::parse($date)->format('m/d/Y');
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

    private function normalizeSignaturePath(?string $value): ?string
    {
        $normalizedPath = $this->normalizeText($value);

        if ($normalizedPath === null) {
            return null;
        }

        if (preg_match('#^[a-z][a-z0-9+.\-]*://#i', $normalizedPath) === 1) {
            $parsedPath = parse_url($normalizedPath, PHP_URL_PATH);
            $normalizedPath = is_string($parsedPath) ? $parsedPath : '';
        }

        $normalizedPath = str_replace('\\', '/', rawurldecode($normalizedPath));
        $normalizedPath = explode('?', $normalizedPath, 2)[0];
        $normalizedPath = explode('#', $normalizedPath, 2)[0];
        $normalizedPath = preg_replace(
            '#^/?(?:storage/app/public/|public/storage/|storage/)#i',
            '',
            $normalizedPath,
        ) ?? $normalizedPath;

        foreach ([
            '/storage/app/public/',
            '/public/storage/',
            '/storage/',
        ] as $marker) {
            $markerPosition = stripos($normalizedPath, $marker);

            if ($markerPosition === false) {
                continue;
            }

            $normalizedPath = substr(
                $normalizedPath,
                $markerPosition + strlen($marker),
            );

            break;
        }

        $normalizedPath = ltrim($normalizedPath, '/');
        $normalizedPath = preg_replace(
            '#^(?:app/public/|public/)+#i',
            '',
            $normalizedPath,
        ) ?? $normalizedPath;
        $normalizedPath = ltrim($normalizedPath, '/');

        return $normalizedPath !== '' ? $normalizedPath : null;
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

    private function resolveWorkbookPaymentModeValue(?string $value): ?string
    {
        $payday = $this->normalizePaydayValue($value);

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

    private function resolveWorkbookPaymentMode(?LoanRequestPerson $applicant): ?string
    {
        return $this->resolveWorkbookPaymentModeValue($applicant?->payday);
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
        ?CarbonInterface $approvedAt,
        ?int $approvedTerm,
    ): ?CarbonInterface {
        if (
            $approvedAt === null
            || $approvedTerm === null
            || $approvedTerm <= 0
        ) {
            return null;
        }

        return $approvedAt->copy()->addMonthsNoOverflow($approvedTerm);
    }

    private function resolveDocumentDate(LoanRequest $loanRequest): ?CarbonInterface
    {
        if ($loanRequest->approved_at instanceof CarbonInterface) {
            return $loanRequest->approved_at;
        }

        $approvedAt = trim((string) $loanRequest->approved_at);

        if ($approvedAt !== '') {
            try {
                return Carbon::parse($approvedAt);
            } catch (Throwable) {
            }
        }

        if ($loanRequest->reviewed_at instanceof CarbonInterface) {
            return $loanRequest->reviewed_at;
        }

        $reviewedAt = trim((string) $loanRequest->reviewed_at);

        if ($reviewedAt === '') {
            return null;
        }

        try {
            return Carbon::parse($reviewedAt);
        } catch (Throwable) {
            return null;
        }
    }

    private function isBlankString(mixed $value): bool
    {
        if (! is_scalar($value)) {
            return true;
        }

        return trim((string) $value) === '';
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

    private function formatPercentWords(float|int|string|null $value): ?string
    {
        if ($value === null || ! is_numeric((string) $value)) {
            return null;
        }

        $percentage = round((float) $value * 100, 2);
        $percentageLabel = fmod($percentage, 1.0) === 0.0
            ? number_format($percentage, 0, '.', '')
            : number_format($percentage, 2, '.', '');

        if (! class_exists(NumberFormatter::class)) {
            return $percentageLabel.' PERCENT ('.$percentageLabel.'%)';
        }

        try {
            $formatter = new NumberFormatter('en', NumberFormatter::SPELLOUT);
            $formatted = $formatter->format((int) round($percentage));

            if (! is_string($formatted) || trim($formatted) === '') {
                return $percentageLabel.' PERCENT ('.$percentageLabel.'%)';
            }

            $words = strtoupper(str_replace('-', ' ', $formatted));
            $words = preg_replace('/\s+/', ' ', $words);

            return trim((string) $words).' PERCENT ('.$percentageLabel.'%)';
        } catch (Throwable) {
            return $percentageLabel.' PERCENT ('.$percentageLabel.'%)';
        }
    }

    private function sumAmounts(float|int|null ...$values): ?float
    {
        $sum = 0.0;
        $hasValue = false;

        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            $sum += (float) $value;
            $hasValue = true;
        }

        return $hasValue ? $sum : null;
    }

    private function roundCurrency(float|int|null $value): ?float
    {
        if ($value === null) {
            return null;
        }

        return round((float) $value, 2);
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

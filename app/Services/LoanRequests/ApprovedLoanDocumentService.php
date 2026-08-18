<?php

namespace App\Services\LoanRequests;

use App\LoanRequestDocumentKey;
use App\LoanRequestStatus;
use App\Models\LoanRequest;
use App\Services\LoanRequests\PdfFieldMaps\AffidavitUndertakingPdfFieldMap;
use App\Services\LoanRequests\PdfFieldMaps\AtmSalaryDeductionWaiverPdfFieldMap;
use App\Services\LoanRequests\PdfFieldMaps\DepedSalaryDeductionWaiverPdfFieldMap;
use App\Services\LoanRequests\PdfFieldMaps\GeneraliApplicationFormPdfFieldMap;
use App\Services\LoanRequests\PdfFieldMaps\GeneraliPdfFieldMap;
use App\Services\LoanRequests\PdfFieldMaps\GrepalifePdfFieldMap;
use App\Services\LoanRequests\PdfFieldMaps\LoanInformationPdfFieldMap;
use App\Services\LoanRequests\PdfFieldMaps\PensionDeductionWaiverPdfFieldMap;
use App\Services\LoanRequests\PdfFieldMaps\UndertakingBarangayPdfFieldMap;
use App\Services\OrganizationSettingsService;
use App\Support\DocumentFilename;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
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
        'loan_information' => 'loan information sheet.pdf',
        'generali' => 'generali.pdf',
        'deped_salary_deduction_waiver' => 'deped-salary-deduction-waiver.pdf',
        'pension_deduction_waiver' => 'pension-deduction-waiver.pdf',
        'atm_salary_deduction_waiver' => 'atm-salary-deduction-waiver.pdf',
        'generali_application_form' => 'generali-application-form.pdf',
    ];

    /**
     * @var array<string, string>
     */
    private const ZIP_DOCUMENT_NAMES = [
        'application_form' => '01-Application-Form.pdf',
        'grepalife' => '02-GREPALIFE.pdf',
        'affidavit_undertaking' => '03-Affidavit-of-Undertaking.pdf',
        'loan_information' => '04-Loan-Information.pdf',
        'plan_of_payment' => '05-Plan-of-Payment.pdf',
        'disclosure_statement' => '06-Disclosure-Statement.pdf',
        'promissory_note' => '07-Promissory-Note.pdf',
        'undertaking_barangay' => '08-Undertaking-Barangay-Officials.pdf',
        'loan_security_agreement' => '09-Loan-Security-Agreement.pdf',
        'generali' => '10-Generali-Health-Statement.pdf',
        'authority_to_deduct' => '11-Authority-to-Deduct.pdf',
        'deped_salary_deduction_waiver' => '12-DepEd-Salary-Deduction-Waiver.pdf',
        'pension_deduction_waiver' => '13-Pension-Deduction-Waiver.pdf',
        'atm_salary_deduction_waiver' => '15-ATM-Salary-Deduction-Waiver.pdf',
        'generali_application_form' => '14-Generali-Application-Form.pdf',
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
        private LoanInformationPdfFieldMap $loanInformationPdfFieldMap,
        private GeneraliPdfFieldMap $generaliPdfFieldMap,
        private PromissoryNotePdfService $promissoryNotePdfService,
        private PlanOfPaymentPdfService $planOfPaymentPdfService,
        private DisclosureStatementPdfService $disclosureStatementPdfService,
        private AuthorityToDeductPdfService $authorityToDeductPdfService,
        private DepedSalaryDeductionWaiverPdfFieldMap $depedSalaryDeductionWaiverPdfFieldMap,
        private PensionDeductionWaiverPdfFieldMap $pensionDeductionWaiverPdfFieldMap,
        private AtmSalaryDeductionWaiverPdfFieldMap $atmSalaryDeductionWaiverPdfFieldMap,
        private GeneraliApplicationFormPdfFieldMap $generaliApplicationFormPdfFieldMap,
        private ApprovedLoanDocumentDataBuilder $documentDataBuilder,
    ) {}

    public function applicationForm(LoanRequest $loanRequest): Response
    {
        $loanRequest->loadMissing(
            'people',
            'reviewedBy.adminProfile',
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

    public function generali(LoanRequest $loanRequest): Response
    {
        return $this->downloadApprovedDocument(
            $loanRequest,
            'generali',
            'application/pdf',
            function (string $outputPath, array $documentData): void {
                $this->approvedLoanPdfTemplateService->generate(
                    self::PDF_TEMPLATE_FILENAMES['generali'],
                    $outputPath,
                    $documentData,
                    $this->generaliPdfFieldMap,
                );
            },
        );
    }

    public function authorityToDeduct(LoanRequest $loanRequest): Response
    {
        return $this->downloadApprovedDocument(
            $loanRequest,
            'authority_to_deduct',
            'application/pdf',
            function (string $outputPath, array $documentData): void {
                $this->authorityToDeductPdfService->generate($outputPath, $documentData);
            },
        );
    }

    public function depedSalaryDeductionWaiver(LoanRequest $loanRequest): Response
    {
        return $this->downloadApprovedDocument(
            $loanRequest,
            'deped_salary_deduction_waiver',
            'application/pdf',
            function (string $outputPath, array $documentData): void {
                $this->approvedLoanPdfTemplateService->generate(
                    self::PDF_TEMPLATE_FILENAMES['deped_salary_deduction_waiver'],
                    $outputPath,
                    $documentData,
                    $this->depedSalaryDeductionWaiverPdfFieldMap,
                );
            },
        );
    }

    public function pensionDeductionWaiver(LoanRequest $loanRequest): Response
    {
        return $this->downloadApprovedDocument(
            $loanRequest,
            'pension_deduction_waiver',
            'application/pdf',
            function (string $outputPath, array $documentData): void {
                $this->approvedLoanPdfTemplateService->generate(
                    self::PDF_TEMPLATE_FILENAMES['pension_deduction_waiver'],
                    $outputPath,
                    $documentData,
                    $this->pensionDeductionWaiverPdfFieldMap,
                );
            },
        );
    }

    public function atmSalaryDeductionWaiver(LoanRequest $loanRequest): Response
    {
        return $this->downloadApprovedDocument(
            $loanRequest,
            'atm_salary_deduction_waiver',
            'application/pdf',
            function (string $outputPath, array $documentData): void {
                $this->approvedLoanPdfTemplateService->generate(
                    self::PDF_TEMPLATE_FILENAMES['atm_salary_deduction_waiver'],
                    $outputPath,
                    $documentData,
                    $this->atmSalaryDeductionWaiverPdfFieldMap,
                );
            },
        );
    }

    public function generaliApplicationForm(LoanRequest $loanRequest): Response
    {
        return $this->downloadApprovedDocument(
            $loanRequest,
            'generali_application_form',
            'application/pdf',
            function (string $outputPath, array $documentData): void {
                $this->approvedLoanPdfTemplateService->generate(
                    self::PDF_TEMPLATE_FILENAMES['generali_application_form'],
                    $outputPath,
                    $documentData,
                    $this->generaliApplicationFormPdfFieldMap,
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
                $this->approvedLoanPdfTemplateService->generate(
                    self::PDF_TEMPLATE_FILENAMES['loan_information'],
                    $outputPath,
                    $documentData,
                    $this->loanInformationPdfFieldMap,
                );
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
        $built = $this->buildZipArchive($loanRequest);

        return $this->downloadFile(
            $built['path'],
            $built['filename'],
            'application/zip',
            $built['workingDirectory'],
        );
    }

    /**
     * Generates every applicable approved-loan document and bundles them
     * into a ZIP, without streaming a response -- shared by the synchronous
     * packageZip() download and GenerateApprovedLoanDocumentPackageJob's
     * queued path. Callers own workingDirectory cleanup on success; on
     * failure this method cleans up after itself before rethrowing.
     *
     * @return array{path: string, filename: string, workingDirectory: string}
     */
    public function buildZipArchive(LoanRequest $loanRequest): array
    {
        $this->ensureApproved($loanRequest);
        $loanRequest->loadMissing(
            'people',
            'reviewedBy.adminProfile',
            'user',
        );

        $workingDirectory = $this->makeWorkingDirectory($loanRequest);
        $documentDirectory = $workingDirectory.DIRECTORY_SEPARATOR.'documents';
        File::ensureDirectoryExists($documentDirectory);

        try {
            $documentData = $this->buildDocumentData($loanRequest);
            $flatValues = $this->loanRequestDataService->loadFlatValues($loanRequest);
            $includeAffidavitUndertaking = $this->documentCatalog->isApplicable(
                LoanRequestDocumentKey::AffidavitUndertaking,
                $loanRequest,
                $flatValues,
            );
            $includeUndertakingBarangay = $this->documentCatalog->isApplicable(
                LoanRequestDocumentKey::UndertakingBarangay,
                $loanRequest,
                $flatValues,
            );
            $includeAuthorityToDeduct = $this->documentCatalog->isApplicable(
                LoanRequestDocumentKey::AuthorityToDeduct,
                $loanRequest,
                $flatValues,
            );
            $includeDepedSalaryDeductionWaiver = $this->documentCatalog->isApplicable(
                LoanRequestDocumentKey::DepedSalaryDeductionWaiver,
                $loanRequest,
                $flatValues,
            );
            $includePensionDeductionWaiver = $this->documentCatalog->isApplicable(
                LoanRequestDocumentKey::PensionDeductionWaiver,
                $loanRequest,
                $flatValues,
            );
            // Also requires the real PDF template to exist -- unlike the other
            // document types here, no blank template has been supplied for this
            // one yet (see AtmSalaryDeductionWaiverPdfFieldMap's docblock), and
            // packageZip() generates unconditionally rather than going through
            // the readiness/blockers gate that protects the single-document
            // generation flow. Skip it silently until the template lands instead
            // of crashing the whole ZIP download for an applicable borrower.
            $includeAtmSalaryDeductionWaiver = $this->documentCatalog->isApplicable(
                LoanRequestDocumentKey::AtmSalaryDeductionWaiver,
                $loanRequest,
                $flatValues,
            ) && $this->documentCatalog->templateBlockers(LoanRequestDocumentKey::AtmSalaryDeductionWaiver) === [];

            $applicationFormPath = $documentDirectory.DIRECTORY_SEPARATOR.self::ZIP_DOCUMENT_NAMES['application_form'];
            $grepalifePath = $documentDirectory.DIRECTORY_SEPARATOR.self::ZIP_DOCUMENT_NAMES['grepalife'];
            $affidavitUndertakingPath = $documentDirectory.DIRECTORY_SEPARATOR.self::ZIP_DOCUMENT_NAMES['affidavit_undertaking'];
            $loanInformationPath = $documentDirectory.DIRECTORY_SEPARATOR.self::ZIP_DOCUMENT_NAMES['loan_information'];
            $planOfPaymentPath = $documentDirectory.DIRECTORY_SEPARATOR.self::ZIP_DOCUMENT_NAMES['plan_of_payment'];
            $disclosureStatementPath = $documentDirectory.DIRECTORY_SEPARATOR.self::ZIP_DOCUMENT_NAMES['disclosure_statement'];
            $promissoryNotePath = $documentDirectory.DIRECTORY_SEPARATOR.self::ZIP_DOCUMENT_NAMES['promissory_note'];
            $undertakingBarangayPath = $documentDirectory.DIRECTORY_SEPARATOR.self::ZIP_DOCUMENT_NAMES['undertaking_barangay'];
            $loanSecurityAgreementPath = $documentDirectory.DIRECTORY_SEPARATOR.self::ZIP_DOCUMENT_NAMES['loan_security_agreement'];
            $generaliPath = $documentDirectory.DIRECTORY_SEPARATOR.self::ZIP_DOCUMENT_NAMES['generali'];
            $authorityToDeductPath = $documentDirectory.DIRECTORY_SEPARATOR.self::ZIP_DOCUMENT_NAMES['authority_to_deduct'];
            $depedSalaryDeductionWaiverPath = $documentDirectory.DIRECTORY_SEPARATOR.self::ZIP_DOCUMENT_NAMES['deped_salary_deduction_waiver'];
            $pensionDeductionWaiverPath = $documentDirectory.DIRECTORY_SEPARATOR.self::ZIP_DOCUMENT_NAMES['pension_deduction_waiver'];
            $atmSalaryDeductionWaiverPath = $documentDirectory.DIRECTORY_SEPARATOR.self::ZIP_DOCUMENT_NAMES['atm_salary_deduction_waiver'];
            $generaliApplicationFormPath = $documentDirectory.DIRECTORY_SEPARATOR.self::ZIP_DOCUMENT_NAMES['generali_application_form'];

            $this->loanRequestPdfService->saveToPath($loanRequest, $applicationFormPath);
            $this->approvedLoanImageTemplatePdfService->generate(
                self::GREPALIFE_IMAGE_TEMPLATE_PAGES,
                $grepalifePath,
                $documentData,
                $this->grepalifePdfFieldMap,
            );
            if ($includeAffidavitUndertaking) {
                $this->approvedLoanPdfTemplateService->generate(
                    self::PDF_TEMPLATE_FILENAMES['affidavit_undertaking'],
                    $affidavitUndertakingPath,
                    $documentData,
                    $this->affidavitUndertakingPdfFieldMap,
                );
            }
            $this->approvedLoanPdfTemplateService->generate(
                self::PDF_TEMPLATE_FILENAMES['loan_information'],
                $loanInformationPath,
                $documentData,
                $this->loanInformationPdfFieldMap,
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
            if ($includeUndertakingBarangay) {
                $this->approvedLoanPdfTemplateService->generate(
                    self::PDF_TEMPLATE_FILENAMES['undertaking_barangay'],
                    $undertakingBarangayPath,
                    $documentData,
                    $this->undertakingBarangayPdfFieldMap,
                );
            }
            $this->loanSecurityAgreementPdfService->generate(
                $loanSecurityAgreementPath,
                $documentData,
            );
            $this->approvedLoanPdfTemplateService->generate(
                self::PDF_TEMPLATE_FILENAMES['generali'],
                $generaliPath,
                $documentData,
                $this->generaliPdfFieldMap,
            );
            if ($includeAuthorityToDeduct) {
                $this->authorityToDeductPdfService->generate(
                    $authorityToDeductPath,
                    $documentData,
                );
            }
            if ($includeDepedSalaryDeductionWaiver) {
                $this->approvedLoanPdfTemplateService->generate(
                    self::PDF_TEMPLATE_FILENAMES['deped_salary_deduction_waiver'],
                    $depedSalaryDeductionWaiverPath,
                    $documentData,
                    $this->depedSalaryDeductionWaiverPdfFieldMap,
                );
            }
            if ($includePensionDeductionWaiver) {
                $this->approvedLoanPdfTemplateService->generate(
                    self::PDF_TEMPLATE_FILENAMES['pension_deduction_waiver'],
                    $pensionDeductionWaiverPath,
                    $documentData,
                    $this->pensionDeductionWaiverPdfFieldMap,
                );
            }
            if ($includeAtmSalaryDeductionWaiver) {
                $this->approvedLoanPdfTemplateService->generate(
                    self::PDF_TEMPLATE_FILENAMES['atm_salary_deduction_waiver'],
                    $atmSalaryDeductionWaiverPath,
                    $documentData,
                    $this->atmSalaryDeductionWaiverPdfFieldMap,
                );
            }
            $this->approvedLoanPdfTemplateService->generate(
                self::PDF_TEMPLATE_FILENAMES['generali_application_form'],
                $generaliApplicationFormPath,
                $documentData,
                $this->generaliApplicationFormPdfFieldMap,
            );

            $zipFilename = DocumentFilename::build(
                $loanRequest->reference,
                'APPROVED-DOCUMENTS',
                'zip',
            );
            $zipPath = $workingDirectory.DIRECTORY_SEPARATOR.$zipFilename;

            $this->createZipArchive($zipPath, array_values(array_filter([
                $applicationFormPath,
                $grepalifePath,
                $includeAffidavitUndertaking ? $affidavitUndertakingPath : null,
                $loanInformationPath,
                $planOfPaymentPath,
                $disclosureStatementPath,
                $promissoryNotePath,
                $includeUndertakingBarangay ? $undertakingBarangayPath : null,
                $loanSecurityAgreementPath,
                $generaliPath,
                $includeAuthorityToDeduct ? $authorityToDeductPath : null,
                $includeDepedSalaryDeductionWaiver ? $depedSalaryDeductionWaiverPath : null,
                $includePensionDeductionWaiver ? $pensionDeductionWaiverPath : null,
                $includeAtmSalaryDeductionWaiver ? $atmSalaryDeductionWaiverPath : null,
                $generaliApplicationFormPath,
            ])));
        } catch (Throwable $exception) {
            File::deleteDirectory($workingDirectory);
            throw $exception;
        }

        return [
            'path' => $zipPath,
            'filename' => $zipFilename,
            'workingDirectory' => $workingDirectory,
        ];
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
                    $this->approvedLoanPdfTemplateService->generate(
                        self::PDF_TEMPLATE_FILENAMES['loan_information'],
                        $path,
                        $documentData,
                        $this->loanInformationPdfFieldMap,
                    );
                },
            ),
            LoanRequestDocumentKey::DisclosureStatement => $this->generatePdfDocumentToPath(
                $outputPath,
                $documentKey,
                function (string $path) use ($documentData): void {
                    $this->disclosureStatementPdfService->generate($path, $documentData);
                },
            ),
            LoanRequestDocumentKey::Generali => $this->generatePdfDocumentToPath(
                $outputPath,
                $documentKey,
                function (string $path) use ($documentData): void {
                    $this->approvedLoanPdfTemplateService->generate(
                        self::PDF_TEMPLATE_FILENAMES['generali'],
                        $path,
                        $documentData,
                        $this->generaliPdfFieldMap,
                    );
                },
            ),
            LoanRequestDocumentKey::AuthorityToDeduct => $this->generatePdfDocumentToPath(
                $outputPath,
                $documentKey,
                function (string $path) use ($documentData): void {
                    $this->authorityToDeductPdfService->generate($path, $documentData);
                },
            ),
            LoanRequestDocumentKey::DepedSalaryDeductionWaiver => $this->generatePdfDocumentToPath(
                $outputPath,
                $documentKey,
                function (string $path) use ($documentData): void {
                    $this->approvedLoanPdfTemplateService->generate(
                        self::PDF_TEMPLATE_FILENAMES['deped_salary_deduction_waiver'],
                        $path,
                        $documentData,
                        $this->depedSalaryDeductionWaiverPdfFieldMap,
                    );
                },
            ),
            LoanRequestDocumentKey::PensionDeductionWaiver => $this->generatePdfDocumentToPath(
                $outputPath,
                $documentKey,
                function (string $path) use ($documentData): void {
                    $this->approvedLoanPdfTemplateService->generate(
                        self::PDF_TEMPLATE_FILENAMES['pension_deduction_waiver'],
                        $path,
                        $documentData,
                        $this->pensionDeductionWaiverPdfFieldMap,
                    );
                },
            ),
            LoanRequestDocumentKey::AtmSalaryDeductionWaiver => $this->generatePdfDocumentToPath(
                $outputPath,
                $documentKey,
                function (string $path) use ($documentData): void {
                    $this->approvedLoanPdfTemplateService->generate(
                        self::PDF_TEMPLATE_FILENAMES['atm_salary_deduction_waiver'],
                        $path,
                        $documentData,
                        $this->atmSalaryDeductionWaiverPdfFieldMap,
                    );
                },
            ),
            LoanRequestDocumentKey::GeneraliApplicationForm => $this->generatePdfDocumentToPath(
                $outputPath,
                $documentKey,
                function (string $path) use ($documentData): void {
                    $this->approvedLoanPdfTemplateService->generate(
                        self::PDF_TEMPLATE_FILENAMES['generali_application_form'],
                        $path,
                        $documentData,
                        $this->generaliApplicationFormPdfFieldMap,
                    );
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
        $this->ensureApproved($loanRequest, $documentKey);
        $this->ensureApplicable($loanRequest, $documentKey);
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
        return DocumentFilename::build($loanRequest->reference, $documentKey, 'pdf');
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

    /**
     * Truth-in-lending–style disclosures must reach the member before they accept the
     * loan's terms, not only afterward -- so these two document keys are allowed one
     * workflow step earlier than the rest of the approved-document package.
     *
     * @var list<string>
     */
    private const PRE_ACCEPTANCE_DISCLOSURE_KEYS = [
        'loan_information',
        'disclosure_statement',
    ];

    private function ensureApproved(LoanRequest $loanRequest, ?string $documentKey = null): void
    {
        $status = $loanRequest->status instanceof LoanRequestStatus
            ? $loanRequest->status->value
            : (string) $loanRequest->status;

        $allowedStatuses = [LoanRequestStatus::Approved->value];

        if ($documentKey !== null && in_array($documentKey, self::PRE_ACCEPTANCE_DISCLOSURE_KEYS, true)) {
            $allowedStatuses[] = LoanRequestStatus::AwaitingMemberAcceptance->value;
        }

        if (! in_array($status, $allowedStatuses, true)) {
            throw new RuntimeException(
                'Approved loan documents are only available for approved loan requests.',
            );
        }
    }

    private function ensureApplicable(LoanRequest $loanRequest, string $documentKey): void
    {
        $key = LoanRequestDocumentKey::from($documentKey);
        $flatValues = $this->loanRequestDataService->loadFlatValues($loanRequest);

        if (! $this->documentCatalog->isApplicable($key, $loanRequest, $flatValues)) {
            throw new RuntimeException(sprintf(
                '%s is not applicable to this loan request.',
                $key->label(),
            ));
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
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function buildDocumentData(
        LoanRequest $loanRequest,
        array $overrides = [],
        bool $allowDefaultFinancialValues = false,
    ): array {
        if ($overrides !== [] || $allowDefaultFinancialValues) {
            return $this->documentDataBuilder->buildDocumentData(
                $loanRequest,
                $overrides,
                $allowDefaultFinancialValues,
            );
        }

        return Cache::remember(
            sprintf(
                'approved-loan-document-data:%d:%d',
                $loanRequest->id,
                $loanRequest->updated_at?->timestamp ?? 0,
            ),
            90,
            fn () => $this->documentDataBuilder->buildDocumentData(
                $loanRequest,
                $overrides,
                $allowDefaultFinancialValues,
            ),
        );
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

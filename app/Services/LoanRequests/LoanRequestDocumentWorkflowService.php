<?php

namespace App\Services\LoanRequests;

use App\LoanRequestDocumentKey;
use App\LoanRequestDocumentReadinessStatus;
use App\LoanRequestWorkflowVersion;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestDocument;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

class LoanRequestDocumentWorkflowService
{
    /**
     * @var array<string, list<string>>
     */
    private const DOCUMENT_REQUIREMENTS = [
        'application_form' => [],
        'grepalife' => [
            'beneficiary_primary_name',
            'beneficiary_primary_relationship',
            'health_smoker',
            'health_hypertension',
            'health_diabetes',
            'health_recent_hospitalization',
        ],
        'affidavit_undertaking' => [],
        'authorization' => [
            'authorized_recipient_name',
            'authorized_recipient_relationship',
            'authorized_recipient_contact',
        ],
        'loan_information' => [
            'service_charge_rate',
            'insurance_rate',
            'insurance_term',
            'loan_security_rate',
            'documentary_stamp_rate',
            'notarial_fee',
            'penalty_rate_per_month',
            'witness_one_name',
            'witness_two_name',
        ],
        'plan_of_payment' => [
            'service_charge_rate',
            'insurance_rate',
            'insurance_term',
            'loan_security_rate',
            'documentary_stamp_rate',
            'notarial_fee',
            'penalty_rate_per_month',
            'witness_one_name',
            'witness_two_name',
        ],
        'disclosure_statement' => [
            'service_charge_rate',
            'insurance_rate',
            'insurance_term',
            'loan_security_rate',
            'documentary_stamp_rate',
            'notarial_fee',
            'penalty_rate_per_month',
            'witness_one_name',
            'witness_two_name',
        ],
        'promissory_note' => [
            'service_charge_rate',
            'insurance_rate',
            'insurance_term',
            'loan_security_rate',
            'documentary_stamp_rate',
            'notarial_fee',
            'penalty_rate_per_month',
            'witness_one_name',
            'witness_two_name',
        ],
        'undertaking_barangay' => [
            'barangay_name',
            'barangay_official_name',
            'barangay_official_title',
        ],
        'loan_security_agreement' => [],
    ];

    /**
     * @var list<string>
     */
    private const TERM_REQUIREMENT_DOCUMENTS = [
        'grepalife',
        'affidavit_undertaking',
        'authorization',
        'loan_information',
        'plan_of_payment',
        'disclosure_statement',
        'promissory_note',
        'undertaking_barangay',
        'loan_security_agreement',
    ];

    public function __construct(
        private LoanRequestDataService $dataService,
        private ApprovedLoanDocumentService $approvedLoanDocumentService,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function serializeChecklist(LoanRequest $loanRequest): array
    {
        return $this->refreshChecklist($loanRequest)
            ->map(fn (LoanRequestDocument $document): array => [
                'key' => $document->document_key,
                'label' => LoanRequestDocumentKey::from($document->document_key)->label(),
                'is_applicable' => $document->is_applicable,
                'status' => $document->readiness_status?->value
                    ?? LoanRequestDocumentReadinessStatus::NotStarted->value,
                'status_label' => $document->readiness_status?->label()
                    ?? LoanRequestDocumentReadinessStatus::NotStarted->label(),
                'template_version' => $document->template_version,
                'generated_at' => $document->generated_at?->toDateTimeString(),
                'generated_by' => $document->generatedBy?->display_code,
                'generated_filename' => $document->generated_filename,
                'generated_mime_type' => $document->generated_mime_type,
                'generated_version' => $document->generated_version,
                'source_version' => $document->source_version,
                'blockers' => $document->failure_information_json['blockers'] ?? [],
                'failure_message' => $document->failure_information_json['message'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, LoanRequestDocument>
     */
    public function refreshChecklist(LoanRequest $loanRequest)
    {
        $loanRequest->loadMissing('documents', 'dataEntries', 'people', 'user');

        foreach (LoanRequestDocumentKey::cases() as $documentKey) {
            $state = $this->evaluateDocumentState($loanRequest, $documentKey);
            $document = LoanRequestDocument::query()->firstOrNew([
                'loan_request_id' => $loanRequest->id,
                'document_key' => $documentKey->value,
            ]);

            $previousSourceHash = $document->source_hash;
            $previousSourceVersion = (int) ($document->source_version ?? 0);
            $sourceVersion = $previousSourceHash !== $state['source_hash']
                ? max(1, $previousSourceVersion + 1)
                : max(1, $previousSourceVersion);

            $readinessStatus = $state['status'];

            if (
                $document->generated_path !== null
                && $document->generated_path !== ''
                && $document->generated_version !== null
                && $previousSourceHash !== null
                && $previousSourceHash !== $state['source_hash']
                && $document->readiness_status === LoanRequestDocumentReadinessStatus::GeneratedCurrent
            ) {
                $readinessStatus = LoanRequestDocumentReadinessStatus::GeneratedStale;
            }

            $document->fill([
                'is_applicable' => $state['is_applicable'],
                'readiness_status' => $readinessStatus,
                'template_version' => $this->approvedLoanDocumentService->templateVersionFor(
                    $documentKey,
                ),
                'source_hash' => $state['source_hash'],
                'source_version' => $sourceVersion,
                'failure_information_json' => $state['failure_information'],
                'metadata_json' => [
                    'required_fields' => $state['required_fields'],
                    'workflow_version' => $this->workflowVersionValue($loanRequest),
                ],
            ]);
            $document->save();
        }

        return LoanRequestDocument::query()
            ->with('generatedBy.adminProfile')
            ->where('loan_request_id', $loanRequest->id)
            ->orderBy('document_key')
            ->get();
    }

    /**
     * @return list<string>
     */
    public function blockersForRecommendation(LoanRequest $loanRequest): array
    {
        $documents = $this->refreshChecklist($loanRequest);
        $blockers = [];

        foreach ($documents as $document) {
            if (! $document->is_applicable) {
                continue;
            }

            if ($document->readiness_status !== LoanRequestDocumentReadinessStatus::GeneratedCurrent) {
                $blockers[] = sprintf(
                    '%s is %s.',
                    LoanRequestDocumentKey::from($document->document_key)->label(),
                    $document->readiness_status?->label()
                        ?? LoanRequestDocumentReadinessStatus::Incomplete->label(),
                );
            }

            foreach (($document->failure_information_json['blockers'] ?? []) as $message) {
                if (is_string($message) && trim($message) !== '') {
                    $blockers[] = sprintf(
                        '%s: %s',
                        LoanRequestDocumentKey::from($document->document_key)->label(),
                        $message,
                    );
                }
            }
        }

        return array_values(array_unique($blockers));
    }

    public function ensureRecommendationReady(LoanRequest $loanRequest): void
    {
        $blockers = $this->blockersForRecommendation($loanRequest);

        if ($blockers !== []) {
            throw ValidationException::withMessages([
                'documents' => implode(' ', $blockers),
            ]);
        }
    }

    /**
     * @return array<int, array{key:string, status:string, message:string|null}>
     */
    public function generateAll(LoanRequest $loanRequest, AppUser $actor): array
    {
        $results = [];

        foreach ($this->refreshChecklist($loanRequest) as $document) {
            $documentKey = LoanRequestDocumentKey::from($document->document_key);

            if (! $document->is_applicable) {
                $results[] = [
                    'key' => $documentKey->value,
                    'status' => LoanRequestDocumentReadinessStatus::NotApplicable->value,
                    'message' => 'Document not applicable.',
                ];

                continue;
            }

            $blockers = $document->failure_information_json['blockers'] ?? [];

            if ($blockers !== []) {
                $results[] = [
                    'key' => $documentKey->value,
                    'status' => $document->readiness_status?->value
                        ?? LoanRequestDocumentReadinessStatus::Incomplete->value,
                    'message' => implode(' ', array_filter($blockers, 'is_string')),
                ];

                continue;
            }

            $generatedDocument = $this->generateDocument(
                $loanRequest,
                $documentKey,
                $actor,
            );

            $results[] = [
                'key' => $documentKey->value,
                'status' => $generatedDocument->readiness_status?->value
                    ?? LoanRequestDocumentReadinessStatus::GeneratedCurrent->value,
                'message' => $generatedDocument->failure_information_json['message'] ?? null,
            ];
        }

        return $results;
    }

    public function generateDocument(
        LoanRequest $loanRequest,
        LoanRequestDocumentKey $documentKey,
        AppUser $actor,
    ): LoanRequestDocument {
        $document = $this->refreshChecklist($loanRequest)
            ->firstOrFail(
                fn (LoanRequestDocument $candidate): bool => $candidate->document_key === $documentKey->value,
            );

        $blockers = $document->failure_information_json['blockers'] ?? [];

        if ($blockers !== []) {
            throw ValidationException::withMessages([
                'document' => implode(' ', array_filter($blockers, 'is_string')),
            ]);
        }

        $loanRequest->loadMissing('people', 'user', 'dataEntries');
        $documentData = $this->documentDataForGeneration($loanRequest);
        $nextGeneratedVersion = max(1, (int) ($document->generated_version ?? 0) + 1);
        $extension = in_array(
            $documentKey,
            LoanRequestDocumentKey::workbookDocuments(),
            true,
        )
            ? 'xlsx'
            : 'pdf';
        $relativePath = sprintf(
            'loan-request-documents/%d/%s/v%d.%s',
            $loanRequest->id,
            $documentKey->value,
            $nextGeneratedVersion,
            $extension,
        );
        $absolutePath = storage_path('app/'.$relativePath);
        File::ensureDirectoryExists(dirname($absolutePath));

        try {
            $metadata = $this->approvedLoanDocumentService->generateToPathForKey(
                $loanRequest,
                $documentKey,
                $absolutePath,
                $documentData,
            );
        } catch (\Throwable $exception) {
            $document->fill([
                'readiness_status' => LoanRequestDocumentReadinessStatus::GenerationFailed,
                'failure_information_json' => [
                    'message' => $exception->getMessage(),
                    'blockers' => [],
                ],
            ])->save();

            throw $exception;
        }

        $document->fill([
            'readiness_status' => LoanRequestDocumentReadinessStatus::GeneratedCurrent,
            'generated_version' => $nextGeneratedVersion,
            'generated_disk' => 'local',
            'generated_path' => $relativePath,
            'generated_filename' => $metadata['filename'],
            'generated_mime_type' => $metadata['mime_type'],
            'generated_size_bytes' => File::exists($absolutePath)
                ? File::size($absolutePath)
                : null,
            'generated_by' => $actor->user_id,
            'generated_at' => now(),
            'failure_information_json' => null,
        ])->save();

        return $document->refresh()->loadMissing('generatedBy.adminProfile');
    }

    /**
     * @param  list<string>  $changedFields
     */
    public function markAffectedDocumentsStale(
        LoanRequest $loanRequest,
        array $changedFields = [],
    ): void {
        $documents = $this->refreshChecklist($loanRequest);

        foreach ($documents as $document) {
            if (! $document->is_applicable) {
                continue;
            }

            if (
                $changedFields !== []
                && ! $this->documentUsesFields(
                    LoanRequestDocumentKey::from($document->document_key),
                    $changedFields,
                )
            ) {
                continue;
            }

            if ($document->readiness_status === LoanRequestDocumentReadinessStatus::GeneratedCurrent) {
                $document->fill([
                    'readiness_status' => LoanRequestDocumentReadinessStatus::GeneratedStale,
                ])->save();
            }
        }
    }

    /**
     * @return array{is_applicable:bool, status:LoanRequestDocumentReadinessStatus, source_hash:string, failure_information:array<string,mixed>|null, required_fields:list<string>}
     */
    private function evaluateDocumentState(
        LoanRequest $loanRequest,
        LoanRequestDocumentKey $documentKey,
    ): array {
        $workflowVersion = $this->workflowVersionValue($loanRequest);
        $requiredFields = self::DOCUMENT_REQUIREMENTS[$documentKey->value] ?? [];
        $flatValues = $this->dataService->loadFlatValues($loanRequest);
        $failureInformation = null;
        $status = LoanRequestDocumentReadinessStatus::ReadyToGenerate;

        if ($workflowVersion === LoanRequestWorkflowVersion::LegacyV1->value) {
            if ($documentKey !== LoanRequestDocumentKey::ApplicationForm) {
                $status = LoanRequestDocumentReadinessStatus::LegacyDataIncomplete;
                $failureInformation = [
                    'message' => 'Historical document data unavailable.',
                    'blockers' => ['Historical document data unavailable.'],
                ];
            }

            return [
                'is_applicable' => true,
                'status' => $status,
                'source_hash' => sha1(json_encode([
                    'workflow_version' => $workflowVersion,
                    'document_key' => $documentKey->value,
                    'reference' => $loanRequest->reference,
                ]) ?: $documentKey->value),
                'failure_information' => $failureInformation,
                'required_fields' => $requiredFields,
            ];
        }

        $missingFields = [];

        foreach ($requiredFields as $fieldKey) {
            $value = $flatValues[$fieldKey] ?? null;

            if (is_bool($value)) {
                continue;
            }

            if ($value === null || trim((string) $value) === '') {
                $missingFields[] = $fieldKey;
            }
        }

        if (in_array($documentKey->value, self::TERM_REQUIREMENT_DOCUMENTS, true)) {
            foreach ([
                'recommended_amount',
                'recommended_term',
                'recommended_interest_rate',
                'recommended_payment_frequency',
            ] as $field) {
                $value = $loanRequest->getAttribute($field);

                if ($value === null || trim((string) $value) === '') {
                    $missingFields[] = $field;
                }
            }
        }

        if ($missingFields !== []) {
            $labels = array_map(
                function (string $fieldKey): string {
                    return match ($fieldKey) {
                        'recommended_amount' => 'Recommended amount',
                        'recommended_term' => 'Recommended term',
                        'recommended_interest_rate' => 'Recommended interest rate',
                        'recommended_payment_frequency' => 'Recommended payment frequency',
                        default => $this->dataService->fieldLabel($fieldKey),
                    };
                },
                $missingFields,
            );
            $hasMemberOwnedMissingField = collect($missingFields)
                ->contains(fn (string $fieldKey): bool => $this->dataService->isSensitiveField($fieldKey));
            $status = $hasMemberOwnedMissingField
                ? LoanRequestDocumentReadinessStatus::AwaitingMemberConfirmation
                : LoanRequestDocumentReadinessStatus::Incomplete;
            $failureInformation = [
                'message' => 'Document data is incomplete.',
                'blockers' => array_map(
                    static fn (string $label): string => sprintf(
                        '%s is required.',
                        $label,
                    ),
                    $labels,
                ),
            ];
        } elseif ($this->dataService->hasUnconfirmedSensitiveFields($loanRequest)) {
            $status = LoanRequestDocumentReadinessStatus::AwaitingMemberConfirmation;
            $failureInformation = [
                'message' => 'Sensitive member-provided values are waiting for confirmation.',
                'blockers' => array_map(
                    fn (string $fieldKey): string => sprintf(
                        '%s must be confirmed by the member.',
                        $this->dataService->fieldLabel($fieldKey),
                    ),
                    $this->dataService->unconfirmedSensitiveFields($loanRequest),
                ),
            ];
        }

        return [
            'is_applicable' => true,
            'status' => $status,
            'source_hash' => sha1(json_encode(
                $this->sourceHashPayload($loanRequest, $documentKey, $flatValues),
            ) ?: $documentKey->value),
            'failure_information' => $failureInformation,
            'required_fields' => $requiredFields,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function documentDataForGeneration(LoanRequest $loanRequest): array
    {
        $flatValues = $this->dataService->loadFlatValues($loanRequest);
        $workingLoanRequest = $loanRequest->replicate();
        $workingLoanRequest->id = $loanRequest->id;
        $workingLoanRequest->reference = $loanRequest->reference;
        $workingLoanRequest->setRelation('people', $loanRequest->people);
        $workingLoanRequest->setRelation('user', $loanRequest->user);
        $workingLoanRequest->approved_amount = $loanRequest->recommended_amount;
        $workingLoanRequest->approved_term = $loanRequest->recommended_term;
        $workingLoanRequest->approved_interest_rate = $loanRequest->recommended_interest_rate;
        $workingLoanRequest->reviewed_at = $loanRequest->reviewed_at
            ?? $loanRequest->updated_at
            ?? now();

        return $this->approvedLoanDocumentService->buildDocumentData(
            $workingLoanRequest,
            [
                'reviewer' => [
                    'witness_one_name' => $flatValues['witness_one_name'] ?? null,
                    'witness_two_name' => $flatValues['witness_two_name'] ?? null,
                ],
                'loan' => [
                    'interest_rate_raw' => $loanRequest->recommended_interest_rate,
                    'service_charge_rate_raw' => $flatValues['service_charge_rate'] ?? null,
                    'insurance_rate_raw' => $flatValues['insurance_rate'] ?? null,
                    'insurance_term' => $flatValues['insurance_term'] ?? null,
                    'loan_security_rate_raw' => $flatValues['loan_security_rate'] ?? null,
                    'documentary_stamp_rate_raw' => $flatValues['documentary_stamp_rate'] ?? null,
                    'notarial_fee_raw' => $flatValues['notarial_fee'] ?? null,
                    'penalty_rate_raw' => $flatValues['penalty_rate_per_month'] ?? null,
                    'payment_mode_workbook' => $loanRequest->recommended_payment_frequency,
                ],
                'beneficiaries' => array_values(array_filter([
                    ($flatValues['beneficiary_primary_name'] ?? null) !== null
                        ? [
                            'name' => $flatValues['beneficiary_primary_name'],
                            'relationship' => $flatValues['beneficiary_primary_relationship'] ?? null,
                            'birthdate' => null,
                        ]
                        : null,
                    ($flatValues['beneficiary_secondary_name'] ?? null) !== null
                        ? [
                            'name' => $flatValues['beneficiary_secondary_name'],
                            'relationship' => $flatValues['beneficiary_secondary_relationship'] ?? null,
                            'birthdate' => null,
                        ]
                        : null,
                ])),
            ],
            false,
        );
    }

    /**
     * @param  array<string, mixed>  $flatValues
     * @return array<string, mixed>
     */
    private function sourceHashPayload(
        LoanRequest $loanRequest,
        LoanRequestDocumentKey $documentKey,
        array $flatValues,
    ): array {
        $requiredFields = self::DOCUMENT_REQUIREMENTS[$documentKey->value] ?? [];
        $fieldValues = [];

        foreach ($requiredFields as $fieldKey) {
            $fieldValues[$fieldKey] = $flatValues[$fieldKey] ?? null;
        }

        return [
            'reference' => $loanRequest->reference,
            'document_key' => $documentKey->value,
            'requested_amount' => (string) ($loanRequest->requested_amount ?? ''),
            'requested_term' => (string) ($loanRequest->requested_term ?? ''),
            'recommended_amount' => (string) ($loanRequest->recommended_amount ?? ''),
            'recommended_term' => (string) ($loanRequest->recommended_term ?? ''),
            'recommended_interest_rate' => (string) ($loanRequest->recommended_interest_rate ?? ''),
            'recommended_payment_frequency' => (string) ($loanRequest->recommended_payment_frequency ?? ''),
            'review_remarks' => (string) ($loanRequest->recommendation_remarks ?? ''),
            'required_fields' => $fieldValues,
        ];
    }

    /**
     * @param  list<string>  $changedFields
     */
    private function documentUsesFields(
        LoanRequestDocumentKey $documentKey,
        array $changedFields,
    ): bool {
        if (array_intersect($changedFields, [
            'typecode',
            'requested_amount',
            'requested_term',
            'loan_purpose',
            'availment_status',
            'applicant',
            'co_maker_1',
            'co_maker_2',
            'recommended_amount',
            'recommended_term',
            'recommended_interest_rate',
            'recommended_payment_frequency',
            'recommendation_remarks',
        ]) !== []) {
            return true;
        }

        return array_intersect(
            $changedFields,
            self::DOCUMENT_REQUIREMENTS[$documentKey->value] ?? [],
        ) !== [];
    }

    private function workflowVersionValue(LoanRequest $loanRequest): string
    {
        return $loanRequest->workflow_version instanceof LoanRequestWorkflowVersion
            ? $loanRequest->workflow_version->value
            : (string) ($loanRequest->workflow_version ?? LoanRequestWorkflowVersion::LegacyV1->value);
    }
}

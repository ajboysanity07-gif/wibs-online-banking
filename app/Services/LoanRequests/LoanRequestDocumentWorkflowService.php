<?php

namespace App\Services\LoanRequests;

use App\LoanRequestDocumentKey;
use App\LoanRequestDocumentReadinessStatus;
use App\LoanRequestWorkflowVersion;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestDocument;
use App\Models\LoanRequestPerson;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

class LoanRequestDocumentWorkflowService
{
    /**
     * @var list<string>
     */
    private const REQUIRED_RECOMMENDATION_FIELDS = [
        'recommended_amount',
        'recommended_term',
        'recommended_interest_rate',
        'recommended_payment_frequency',
    ];

    /**
     * @var list<string>
     */
    private const VALID_PAYMENT_FREQUENCIES = [
        'Weekly',
        '15th',
        '30th',
        '15th & 30th',
        'Bi-Weekly',
        'Monthly',
    ];

    public function __construct(
        private LoanRequestDataService $dataService,
        private ApprovedLoanDocumentService $approvedLoanDocumentService,
        private LoanRequestDocumentCatalog $documentCatalog,
        private LoanRequestDocumentStorage $documentStorage,
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
    public function inspectChecklist(LoanRequest $loanRequest): Collection
    {
        $loanRequest->loadMissing('documents', 'dataEntries', 'people', 'user');
        $flatValues = $this->dataService->loadFlatValues($loanRequest);

        return collect(LoanRequestDocumentKey::cases())->map(function (
            LoanRequestDocumentKey $documentKey,
        ) use ($loanRequest, $flatValues): array {
            $state = $this->evaluateDocumentState(
                $loanRequest,
                $documentKey,
                $flatValues,
            );
            /** @var LoanRequestDocument|null $document */
            $document = $loanRequest->documents
                ->firstWhere('document_key', $documentKey->value);

            $previousSourceHash = $document?->source_hash;
            $previousSourceVersion = (int) ($document?->source_version ?? 0);
            $sourceVersion = $previousSourceHash !== $state['source_hash']
                ? max(1, $previousSourceVersion + 1)
                : max(1, $previousSourceVersion);
            $readinessStatus = $state['status'];
            $hasGeneratedArtifact = $document?->generated_path !== null
                && $document?->generated_path !== ''
                && $document?->generated_version !== null
                && $this->documentStorage->fileExists(
                    $document->generated_path,
                    $document->generated_disk,
                );

            if (
                $state['status'] !== LoanRequestDocumentReadinessStatus::NotApplicable
                && $hasGeneratedArtifact
                && $previousSourceHash !== null
            ) {
                if ($previousSourceHash === $state['source_hash']) {
                    $readinessStatus = match ($document?->readiness_status) {
                        LoanRequestDocumentReadinessStatus::GeneratedCurrent => LoanRequestDocumentReadinessStatus::GeneratedCurrent,
                        LoanRequestDocumentReadinessStatus::GeneratedStale => LoanRequestDocumentReadinessStatus::GeneratedStale,
                        default => $readinessStatus,
                    };
                } elseif (
                    in_array($document?->readiness_status, [
                        LoanRequestDocumentReadinessStatus::GeneratedCurrent,
                        LoanRequestDocumentReadinessStatus::GeneratedStale,
                    ], true)
                ) {
                    $readinessStatus = LoanRequestDocumentReadinessStatus::GeneratedStale;
                }
            }

            if (
                $state['status'] !== LoanRequestDocumentReadinessStatus::NotApplicable
                && ! $hasGeneratedArtifact
                && $document?->generated_path !== null
                && $document?->generated_path !== ''
                && in_array($document?->readiness_status, [
                    LoanRequestDocumentReadinessStatus::GeneratedCurrent,
                    LoanRequestDocumentReadinessStatus::GeneratedStale,
                ], true)
            ) {
                $readinessStatus = LoanRequestDocumentReadinessStatus::GenerationFailed;
                $state['failure_information'] = [
                    'message' => 'The generated file is missing from private storage.',
                    'blockers' => [],
                ];
            }

            return [
                'document_key' => $documentKey,
                'document' => $document,
                'fill' => [
                    'is_applicable' => $state['is_applicable'],
                    'readiness_status' => $readinessStatus,
                    'template_version' => $this->documentCatalog->templateVersionFor(
                        $documentKey,
                    ),
                    'source_hash' => $state['source_hash'],
                    'source_version' => $sourceVersion,
                    'failure_information_json' => $state['failure_information'],
                ],
                'metadata_json' => [
                    'required_fields' => $state['required_fields'],
                    'source_fields' => $this->documentCatalog->sourceFieldKeys(
                        $documentKey,
                    ),
                    'workflow_version' => $this->workflowVersionValue($loanRequest),
                ],
            ];
        });
    }

    /**
     * @return \Illuminate\Support\Collection<int, LoanRequestDocument>
     */
    public function refreshChecklist(LoanRequest $loanRequest)
    {
        foreach ($this->inspectChecklist($loanRequest) as $inspection) {
            $documentKey = $inspection['document_key'];
            $document = $inspection['document'] instanceof LoanRequestDocument
                ? $inspection['document']
                : LoanRequestDocument::query()->firstOrNew([
                    'loan_request_id' => $loanRequest->id,
                    'document_key' => $documentKey->value,
                ]);

            $document->fill([
                ...$inspection['fill'],
                'metadata_json' => $inspection['metadata_json'],
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
        return $this->withDocumentGenerationLock($loanRequest, function () use (
            $loanRequest,
            $actor,
        ): array {
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

                try {
                    $generatedDocument = $this->generateDocumentInternal(
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
                } catch (\Throwable $exception) {
                    $failedDocument = LoanRequestDocument::query()
                        ->where('loan_request_id', $loanRequest->id)
                        ->where('document_key', $documentKey->value)
                        ->first();

                    $results[] = [
                        'key' => $documentKey->value,
                        'status' => $failedDocument?->readiness_status?->value
                            ?? LoanRequestDocumentReadinessStatus::GenerationFailed->value,
                        'message' => $failedDocument?->failure_information_json['message']
                            ?? $exception->getMessage(),
                    ];
                }
            }

            return $results;
        });
    }

    public function generateDocument(
        LoanRequest $loanRequest,
        LoanRequestDocumentKey $documentKey,
        AppUser $actor,
    ): LoanRequestDocument {
        return $this->withDocumentGenerationLock($loanRequest, function () use (
            $loanRequest,
            $documentKey,
            $actor,
        ): LoanRequestDocument {
            return $this->generateDocumentInternal(
                $loanRequest,
                $documentKey,
                $actor,
            );
        });
    }

    private function generateDocumentInternal(
        LoanRequest $loanRequest,
        LoanRequestDocumentKey $documentKey,
        AppUser $actor,
    ): LoanRequestDocument {
        $document = $this->refreshChecklist($loanRequest)
            ->firstOrFail(
                fn (LoanRequestDocument $candidate): bool => $candidate->document_key === $documentKey->value,
            );

        if (! $document->is_applicable) {
            throw ValidationException::withMessages([
                'document' => 'This document is not applicable to the current request.',
            ]);
        }

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
        $relativePath = $this->documentStorage->newGeneratedDocumentPath(
            $loanRequest->id,
            $documentKey->value,
            $nextGeneratedVersion,
            $extension,
        );
        $absolutePath = $this->documentStorage->absolutePath($relativePath);
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
            'generated_disk' => $this->documentStorage->documentDisk(),
            'generated_path' => $relativePath,
            'generated_filename' => $metadata['filename'],
            'generated_mime_type' => $metadata['mime_type'],
            'generated_size_bytes' => File::exists($absolutePath)
                ? File::size($absolutePath)
                : null,
            'generated_checksum_sha256' => $this->documentStorage
                ->checksumForAbsolutePath($absolutePath),
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
        if ($changedFields === []) {
            $this->refreshChecklist($loanRequest);

            return;
        }

        $documents = $this->refreshChecklist($loanRequest);

        foreach ($documents as $document) {
            if (! $document->is_applicable) {
                continue;
            }

            if (! $this->documentCatalog->usesChangedFields(
                LoanRequestDocumentKey::from($document->document_key),
                $changedFields,
            )) {
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
     * @param  array<string, mixed>  $flatValues
     * @return array{is_applicable:bool, status:LoanRequestDocumentReadinessStatus, source_hash:string, failure_information:array<string,mixed>|null, required_fields:list<string>}
     */
    private function evaluateDocumentState(
        LoanRequest $loanRequest,
        LoanRequestDocumentKey $documentKey,
        array $flatValues,
    ): array {
        $workflowVersion = $this->workflowVersionValue($loanRequest);
        $requiredFields = $this->effectiveRequiredFields($documentKey, $flatValues);

        if ($workflowVersion === LoanRequestWorkflowVersion::LegacyV1->value) {
            $status = $documentKey === LoanRequestDocumentKey::ApplicationForm
                ? LoanRequestDocumentReadinessStatus::ReadyToGenerate
                : LoanRequestDocumentReadinessStatus::LegacyDataIncomplete;
            $failureInformation = $documentKey === LoanRequestDocumentKey::ApplicationForm
                ? null
                : [
                    'message' => 'Historical document data unavailable.',
                    'blockers' => ['Historical document data unavailable.'],
                ];

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

        $isApplicable = $this->documentCatalog->isApplicable(
            $documentKey,
            $loanRequest,
            $flatValues,
        );

        if (! $isApplicable) {
            return [
                'is_applicable' => false,
                'status' => LoanRequestDocumentReadinessStatus::NotApplicable,
                'source_hash' => sha1(json_encode(
                    $this->sourceHashPayload($loanRequest, $documentKey, $flatValues, false),
                ) ?: $documentKey->value),
                'failure_information' => null,
                'required_fields' => $requiredFields,
            ];
        }

        $blockers = [];
        $memberActionNeeded = false;
        $missingFields = $this->missingRequiredFieldKeys(
            $requiredFields,
            $loanRequest,
            $flatValues,
        );

        foreach ($this->documentCatalog->templateBlockers($documentKey) as $message) {
            $blockers[] = $message;
        }

        foreach ($missingFields as $fieldKey) {
            $blockers[] = sprintf(
                '%s is required.',
                $this->fieldLabel($fieldKey),
            );

            if ($this->dataService->isSensitiveField($fieldKey)) {
                $memberActionNeeded = true;
            }
        }

        foreach ($this->unconfirmedSourceFields($loanRequest, $documentKey) as $fieldKey) {
            $blockers[] = sprintf(
                '%s must be confirmed by the member.',
                $this->fieldLabel($fieldKey),
            );
            $memberActionNeeded = true;
        }

        foreach ($this->financialBlockers($loanRequest, $documentKey, $flatValues) as $message) {
            $blockers[] = $message;
        }

        $blockers = array_values(array_unique(array_filter(
            $blockers,
            static fn (mixed $message): bool => is_string($message) && trim($message) !== '',
        )));
        $status = $blockers === []
            ? LoanRequestDocumentReadinessStatus::ReadyToGenerate
            : ($memberActionNeeded
                ? LoanRequestDocumentReadinessStatus::AwaitingMemberConfirmation
                : LoanRequestDocumentReadinessStatus::Incomplete);

        return [
            'is_applicable' => true,
            'status' => $status,
            'source_hash' => sha1(json_encode(
                $this->sourceHashPayload($loanRequest, $documentKey, $flatValues, true),
            ) ?: $documentKey->value),
            'failure_information' => $blockers === []
                ? null
                : [
                    'message' => $memberActionNeeded
                        ? 'Member confirmation is required before this document can be generated.'
                        : 'Document data is incomplete.',
                    'blockers' => $blockers,
                ],
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
        $workingLoanRequest->recommended_payment_frequency = $loanRequest->recommended_payment_frequency;
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
                    'security_required' => $flatValues['security_required'] ?? null,
                    'documentary_stamp_rate_raw' => $flatValues['documentary_stamp_rate'] ?? null,
                    'notarial_fee_raw' => $flatValues['notarial_fee'] ?? null,
                    'penalty_rate_raw' => $flatValues['penalty_rate_per_month'] ?? null,
                    'payment_mode_workbook' => $loanRequest->recommended_payment_frequency,
                ],
                'processing' => [
                    'notarial_venue' => $flatValues['notarial_venue'] ?? null,
                    'barangay_name' => $flatValues['barangay_name'] ?? null,
                    'barangay_clearance_reference' => $flatValues['barangay_clearance_reference'] ?? null,
                    'barangay_locality' => $flatValues['barangay_locality'] ?? null,
                    'barangay_official_name' => $flatValues['barangay_official_name'] ?? null,
                    'barangay_official_title' => $flatValues['barangay_official_title'] ?? null,
                    'release_method' => $flatValues['release_method'] ?? null,
                    'payout_bank_name' => $flatValues['payout_bank_name'] ?? null,
                    'payout_account_name' => $flatValues['payout_account_name'] ?? null,
                    'payout_account_number' => $flatValues['payout_account_number'] ?? null,
                    'payout_account_type' => $flatValues['payout_account_type'] ?? null,
                    'payout_atm_number' => $flatValues['payout_atm_number'] ?? null,
                    'health_smoker' => $flatValues['health_smoker'] ?? null,
                    'health_hypertension' => $flatValues['health_hypertension'] ?? null,
                    'health_diabetes' => $flatValues['health_diabetes'] ?? null,
                    'health_recent_hospitalization' => $flatValues['health_recent_hospitalization'] ?? null,
                    'health_declaration_notes' => $flatValues['health_declaration_notes'] ?? null,
                ],
                'beneficiaries' => array_values(array_filter([
                    ($flatValues['beneficiary_primary_name'] ?? null) !== null
                        ? [
                            'name' => $flatValues['beneficiary_primary_name'],
                            'relationship' => $flatValues['beneficiary_primary_relationship'] ?? null,
                            'birthdate' => $flatValues['beneficiary_primary_birthdate'] ?? null,
                        ]
                        : null,
                    ($flatValues['beneficiary_secondary_name'] ?? null) !== null
                        ? [
                            'name' => $flatValues['beneficiary_secondary_name'],
                            'relationship' => $flatValues['beneficiary_secondary_relationship'] ?? null,
                            'birthdate' => $flatValues['beneficiary_secondary_birthdate'] ?? null,
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
        bool $isApplicable,
    ): array {
        $fieldValues = [];

        foreach ($this->documentCatalog->sourceFieldKeys($documentKey) as $fieldKey) {
            $fieldValues[$fieldKey] = $flatValues[$fieldKey] ?? null;
        }

        $snapshotValues = [];

        foreach ($this->documentCatalog->sourceSnapshotPaths($documentKey) as $snapshotPath) {
            $snapshotValues[$snapshotPath] = $this->snapshotValueForPath(
                $loanRequest,
                $snapshotPath,
            );
        }

        return [
            'reference' => $loanRequest->reference,
            'document_key' => $documentKey->value,
            'workflow_version' => $this->workflowVersionValue($loanRequest),
            'is_applicable' => $isApplicable,
            'snapshot_values' => $snapshotValues,
            'field_values' => $fieldValues,
        ];
    }

    /**
     * @return list<string>
     */
    private function effectiveRequiredFields(
        LoanRequestDocumentKey $documentKey,
        array $flatValues,
    ): array {
        $requiredFields = $this->documentCatalog->requiredFieldKeys($documentKey);

        if (($flatValues['security_required'] ?? null) === false) {
            $requiredFields = array_values(array_diff($requiredFields, [
                'loan_security_rate',
            ]));
        }

        return $requiredFields;
    }

    /**
     * @param  list<string>  $requiredFields
     * @param  array<string, mixed>  $flatValues
     * @return list<string>
     */
    private function missingRequiredFieldKeys(
        array $requiredFields,
        LoanRequest $loanRequest,
        array $flatValues,
    ): array {
        $missingFields = [];

        foreach ($requiredFields as $fieldKey) {
            if (in_array($fieldKey, self::REQUIRED_RECOMMENDATION_FIELDS, true)) {
                continue;
            }

            $value = $flatValues[$fieldKey] ?? null;

            if ($this->isBlankValue($value)) {
                $missingFields[] = $fieldKey;
            }
        }

        foreach ($this->conditionalRequiredMemberFields($loanRequest, $flatValues) as $fieldKey) {
            if ($this->isBlankValue($flatValues[$fieldKey] ?? null)) {
                $missingFields[] = $fieldKey;
            }
        }

        return array_values(array_unique($missingFields));
    }

    /**
     * @param  array<string, mixed>  $flatValues
     * @return list<string>
     */
    private function conditionalRequiredMemberFields(
        LoanRequest $loanRequest,
        array $flatValues,
    ): array {
        $conditionalFields = [];

        if (
            in_array(
                'beneficiary_secondary_name',
                $this->documentCatalog->sourceFieldKeys(LoanRequestDocumentKey::Grepalife),
                true,
            )
            && ! $this->isBlankValue($flatValues['beneficiary_secondary_name'] ?? null)
        ) {
            $conditionalFields[] = 'beneficiary_secondary_relationship';
            $conditionalFields[] = 'beneficiary_secondary_birthdate';
        }

        return array_values(array_unique($conditionalFields));
    }

    /**
     * @return list<string>
     */
    private function unconfirmedSourceFields(
        LoanRequest $loanRequest,
        LoanRequestDocumentKey $documentKey,
    ): array {
        $sourceFields = $this->documentCatalog->sourceFieldKeys($documentKey);

        return array_values(array_filter(
            $this->dataService->unconfirmedSensitiveFields($loanRequest),
            static fn (string $fieldKey): bool => in_array($fieldKey, $sourceFields, true),
        ));
    }

    /**
     * @param  array<string, mixed>  $flatValues
     * @return list<string>
     */
    private function financialBlockers(
        LoanRequest $loanRequest,
        LoanRequestDocumentKey $documentKey,
        array $flatValues,
    ): array {
        if (! $this->documentCatalog->requiresFinancialRules($documentKey)) {
            return [];
        }

        $blockers = [];
        $recommendationValues = [
            'recommended_amount' => $loanRequest->recommended_amount,
            'recommended_term' => $loanRequest->recommended_term,
            'recommended_interest_rate' => $loanRequest->recommended_interest_rate,
            'recommended_payment_frequency' => $loanRequest->recommended_payment_frequency,
        ];

        foreach ($recommendationValues as $fieldKey => $value) {
            if ($this->isBlankValue($value)) {
                $blockers[] = sprintf(
                    '%s is required.',
                    $this->fieldLabel($fieldKey),
                );
            }
        }

        if (! $this->isPositiveNumericValue($loanRequest->recommended_amount)) {
            $blockers[] = 'Recommended amount must be greater than zero.';
        }

        if (! $this->isPositiveIntegerValue($loanRequest->recommended_term)) {
            $blockers[] = 'Recommended term must be greater than zero.';
        }

        if (! $this->isNumericValue($loanRequest->recommended_interest_rate)) {
            $blockers[] = 'Recommended interest rate must be numeric.';
        }

        if (
            ! $this->isBlankValue($loanRequest->recommended_payment_frequency)
            && ! in_array(
                trim((string) $loanRequest->recommended_payment_frequency),
                self::VALID_PAYMENT_FREQUENCIES,
                true,
            )
        ) {
            $blockers[] = 'Recommended payment frequency is not recognized by the official templates.';
        }

        foreach ([
            'service_charge_rate' => 'Service charge rate must be numeric.',
            'documentary_stamp_rate' => 'Documentary stamp rate must be numeric.',
            'notarial_fee' => 'Notarial fee must be numeric.',
            'penalty_rate_per_month' => 'Penalty rate per month must be numeric.',
        ] as $fieldKey => $message) {
            if (
                ! $this->isBlankValue($flatValues[$fieldKey] ?? null)
                && ! $this->isNumericValue($flatValues[$fieldKey] ?? null)
            ) {
                $blockers[] = $message;
            }
        }

        if (! $this->isNumericValue($flatValues['insurance_rate'] ?? null)) {
            $blockers[] = 'Insurance rate must be numeric.';
        }

        if (! $this->isPositiveIntegerValue($flatValues['insurance_term'] ?? null)) {
            $blockers[] = 'Insurance term must be greater than zero.';
        }

        if (($flatValues['security_required'] ?? null) !== false) {
            if (! $this->isNumericValue($flatValues['loan_security_rate'] ?? null)) {
                $blockers[] = 'Loan security rate must be numeric.';
            }
        }

        return array_values(array_unique($blockers));
    }

    private function snapshotValueForPath(
        LoanRequest $loanRequest,
        string $path,
    ): mixed {
        $loanRequest->loadMissing('people');

        if ($path === 'applicant.') {
            return $this->personSnapshot(
                $loanRequest->people->firstWhere('role', 'applicant'),
            );
        }

        if ($path === 'co_maker_1.') {
            return $this->personSnapshot(
                $loanRequest->people->firstWhere('role', 'co_maker_1'),
            );
        }

        if ($path === 'co_maker_2.') {
            return $this->personSnapshot(
                $loanRequest->people->firstWhere('role', 'co_maker_2'),
            );
        }

        if (! str_starts_with($path, 'loan_request.')) {
            return null;
        }

        $attribute = substr($path, strlen('loan_request.'));

        return $loanRequest->getAttribute($attribute);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function personSnapshot(?LoanRequestPerson $person): ?array
    {
        if (! $person instanceof LoanRequestPerson) {
            return null;
        }

        return [
            'first_name' => $person->first_name,
            'middle_name' => $person->middle_name,
            'last_name' => $person->last_name,
            'birthdate' => $person->birthdate,
            'birthplace_city' => $person->birthplace_city,
            'birthplace_province' => $person->birthplace_province,
            'address1' => $person->address1,
            'address2' => $person->address2,
            'address3' => $person->address3,
            'cell_no' => $person->cell_no,
            'telephone_no' => $person->telephone_no,
            'civil_status' => $person->civil_status,
            'employer_business_name' => $person->employer_business_name,
            'employer_business_address1' => $person->employer_business_address1,
            'employer_business_address2' => $person->employer_business_address2,
            'employer_business_address3' => $person->employer_business_address3,
            'current_position' => $person->current_position,
            'nature_of_business' => $person->nature_of_business,
            'years_in_work_business' => $person->years_in_work_business,
            'gross_monthly_income' => $person->gross_monthly_income,
            'payday' => $person->payday,
        ];
    }

    private function fieldLabel(string $fieldKey): string
    {
        return match ($fieldKey) {
            'recommended_amount' => 'Recommended amount',
            'recommended_term' => 'Recommended term',
            'recommended_interest_rate' => 'Recommended interest rate',
            'recommended_payment_frequency' => 'Recommended payment frequency',
            default => $this->dataService->fieldLabel($fieldKey),
        };
    }

    private function isBlankValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return false;
        }

        return $value === null || trim((string) $value) === '';
    }

    private function isNumericValue(mixed $value): bool
    {
        return ! $this->isBlankValue($value)
            && is_numeric((string) $value);
    }

    private function isPositiveNumericValue(mixed $value): bool
    {
        return $this->isNumericValue($value)
            && (float) $value > 0;
    }

    private function isPositiveIntegerValue(mixed $value): bool
    {
        return $this->isNumericValue($value)
            && (int) round((float) $value) > 0;
    }

    private function workflowVersionValue(LoanRequest $loanRequest): string
    {
        return $loanRequest->workflow_version instanceof LoanRequestWorkflowVersion
            ? $loanRequest->workflow_version->value
            : (string) ($loanRequest->workflow_version ?? LoanRequestWorkflowVersion::LegacyV1->value);
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function withDocumentGenerationLock(
        LoanRequest $loanRequest,
        callable $callback,
    ): mixed {
        $lock = Cache::lock(
            sprintf('loan-workflow:documents:%d', $loanRequest->id),
            120,
        );
        $result = $lock->get($callback);

        if ($result !== false) {
            return $result;
        }

        throw ValidationException::withMessages([
            'documents' => 'Document generation is already running for this request.',
        ]);
    }
}

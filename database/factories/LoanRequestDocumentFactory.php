<?php

namespace Database\Factories;

use App\LoanRequestDocumentReadinessStatus;
use App\Models\LoanRequest;
use App\Models\LoanRequestDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoanRequestDocument>
 */
class LoanRequestDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'loan_request_id' => LoanRequest::factory(),
            'document_key' => 'application_form',
            'is_applicable' => true,
            'readiness_status' => LoanRequestDocumentReadinessStatus::NotStarted,
            'template_version' => 'application-form-v2',
            'source_hash' => sha1((string) fake()->uuid()),
            'source_version' => 1,
            'generated_version' => 0,
            'generated_disk' => null,
            'generated_path' => null,
            'generated_filename' => null,
            'generated_mime_type' => null,
            'generated_size_bytes' => null,
            'generated_checksum_sha256' => null,
            'generated_by' => null,
            'generated_at' => null,
            'failure_information_json' => null,
            'metadata_json' => [],
        ];
    }
}

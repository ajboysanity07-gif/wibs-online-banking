<?php

namespace App\Models;

use App\LoanRequestDocumentReadinessStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanRequestDocument extends Model
{
    /** @use HasFactory<\Database\Factories\LoanRequestDocumentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'loan_request_id',
        'document_key',
        'is_applicable',
        'readiness_status',
        'template_version',
        'source_hash',
        'source_version',
        'generated_version',
        'generated_disk',
        'generated_path',
        'generated_filename',
        'generated_mime_type',
        'generated_size_bytes',
        'generated_by',
        'generated_at',
        'failure_information_json',
        'metadata_json',
    ];

    public function loanRequest(): BelongsTo
    {
        return $this->belongsTo(LoanRequest::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'generated_by', 'user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_applicable' => 'boolean',
            'generated_at' => 'datetime',
            'failure_information_json' => 'array',
            'metadata_json' => 'array',
            'readiness_status' => LoanRequestDocumentReadinessStatus::class,
        ];
    }
}

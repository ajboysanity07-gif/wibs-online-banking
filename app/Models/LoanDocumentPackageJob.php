<?php

namespace App\Models;

use App\LoanDocumentPackageJobStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanDocumentPackageJob extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'loan_request_id',
        'status',
        'zip_disk',
        'zip_path',
        'zip_filename',
        'error_message',
        'requested_by',
        'started_at',
        'completed_at',
    ];

    public function loanRequest(): BelongsTo
    {
        return $this->belongsTo(LoanRequest::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'requested_by', 'user_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, LoanDocumentPackageJobStatus::active(), true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LoanDocumentPackageJobStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}

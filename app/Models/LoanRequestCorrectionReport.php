<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanRequestCorrectionReport extends Model
{
    /** @use HasFactory<\Database\Factories\LoanRequestCorrectionReportFactory> */
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_DISMISSED = 'dismissed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'loan_request_id',
        'user_id',
        'issue_description',
        'correct_information',
        'supporting_note',
        'status',
        'resolved_by',
        'resolved_at',
        'dismissed_by',
        'dismissed_at',
        'admin_notes',
    ];

    public function loanRequest(): BelongsTo
    {
        return $this->belongsTo(LoanRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'user_id', 'user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'resolved_by', 'user_id');
    }

    public function dismissedBy(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'dismissed_by', 'user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }
}

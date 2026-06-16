<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanRequestNotificationEvent extends Model
{
    /** @use HasFactory<\Database\Factories\LoanRequestNotificationEventFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'loan_request_id',
        'event_type',
        'channel',
        'recipient_user_id',
        'recipient',
        'result',
        'queued_at',
        'last_attempt_at',
        'failed_at',
        'attempt_count',
        'retry_count',
        'reminder_attempts',
        'provider_reference',
        'provider_error',
        'sent_at',
        'reminder_sent_at',
        'metadata_json',
    ];

    public function loanRequest(): BelongsTo
    {
        return $this->belongsTo(LoanRequest::class);
    }

    public function recipientUser(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'recipient_user_id', 'user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'queued_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'failed_at' => 'datetime',
            'attempt_count' => 'integer',
            'retry_count' => 'integer',
            'reminder_attempts' => 'integer',
            'sent_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'metadata_json' => 'array',
        ];
    }
}

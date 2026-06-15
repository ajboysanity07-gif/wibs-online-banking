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
            'sent_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'metadata_json' => 'array',
        ];
    }
}

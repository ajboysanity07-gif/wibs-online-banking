<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlinePayment extends Model
{
    /** @use HasFactory<\Database\Factories\OnlinePaymentFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_POSTED = 'posted';

    /**
     * @var list<string>
     */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PAID,
        self::STATUS_FAILED,
        self::STATUS_EXPIRED,
        self::STATUS_CANCELLED,
        self::STATUS_POSTED,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'acctno',
        'loan_number',
        'amount',
        'currency',
        'provider',
        'provider_checkout_id',
        'provider_payment_id',
        'reference_number',
        'status',
        'paid_at',
        'posted_at',
        'posted_by',
        'raw_payload',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'user_id', 'user_id');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'posted_by', 'user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'raw_payload' => 'array',
            'paid_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymongoLoanPayment extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'acctno',
        'loan_number',
        'currency',
        'payment_method',
        'payment_method_label',
        'payment_method_type',
        'base_amount_cents',
        'service_fee_cents',
        'gross_amount_cents',
        'status',
        'provider',
        'provider_checkout_session_id',
        'provider_payment_intent_id',
        'provider_reference_number',
        'checkout_url',
        'metadata',
        'paid_at',
        'expires_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'user_id', 'user_id');
    }

    public function baseAmount(): float
    {
        return $this->base_amount_cents / 100;
    }

    public function serviceFee(): float
    {
        return $this->service_fee_cents / 100;
    }

    public function grossAmount(): float
    {
        return $this->gross_amount_cents / 100;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
            'base_amount_cents' => 'integer',
            'service_fee_cents' => 'integer',
            'gross_amount_cents' => 'integer',
        ];
    }
}

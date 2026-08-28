<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberPaymentAccount extends Model
{
    /** @use HasFactory<\Database\Factories\MemberPaymentAccountFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'member_application_profile_id',
        'label',
        'bank_name',
        'account_name',
        'account_number',
        'account_type',
        'atm_number',
        'bank_branch',
        'atm_holder_name',
        'last_used_at',
    ];

    public function memberApplicationProfile(): BelongsTo
    {
        return $this->belongsTo(MemberApplicationProfile::class);
    }

    public function displayLabel(): string
    {
        $label = trim((string) $this->label);

        if ($label !== '') {
            return $label;
        }

        $bankName = trim((string) $this->bank_name);
        $accountNumber = trim((string) $this->account_number);

        if ($bankName === '' && $accountNumber === '') {
            return 'Saved account';
        }

        $maskedAccountNumber = $accountNumber !== '' && strlen($accountNumber) > 4
            ? '••'.substr($accountNumber, -4)
            : $accountNumber;

        return trim("{$bankName} {$maskedAccountNumber}");
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }
}

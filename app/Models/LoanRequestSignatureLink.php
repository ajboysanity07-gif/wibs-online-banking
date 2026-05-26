<?php

namespace App\Models;

use App\LoanRequestPersonRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanRequestSignatureLink extends Model
{
    /** @use HasFactory<\Database\Factories\LoanRequestSignatureLinkFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'loan_request_id',
        'loan_request_person_id',
        'role',
        'token_hash',
        'expires_at',
        'signed_at',
        'revoked_at',
        'ip_address',
        'user_agent',
    ];

    public function loanRequest(): BelongsTo
    {
        return $this->belongsTo(LoanRequest::class);
    }

    public function loanRequestPerson(): BelongsTo
    {
        return $this->belongsTo(LoanRequestPerson::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => LoanRequestPersonRole::class,
            'expires_at' => 'datetime',
            'signed_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}

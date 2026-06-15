<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanRequestDataEntry extends Model
{
    /** @use HasFactory<\Database\Factories\LoanRequestDataEntryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'loan_request_id',
        'section_key',
        'field_key',
        'owner_type',
        'is_sensitive',
        'confirmed_by_member',
        'confirmed_by_member_at',
        'value_json',
        'metadata_json',
    ];

    public function loanRequest(): BelongsTo
    {
        return $this->belongsTo(LoanRequest::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_sensitive' => 'boolean',
            'confirmed_by_member' => 'boolean',
            'confirmed_by_member_at' => 'datetime',
            'value_json' => 'array',
            'metadata_json' => 'array',
        ];
    }
}

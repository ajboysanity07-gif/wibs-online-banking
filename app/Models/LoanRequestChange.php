<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanRequestChange extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'loan_request_id',
        'changed_by',
        'action',
        'reason',
        'before_json',
        'after_json',
        'changed_fields_json',
    ];

    public function loanRequest(): BelongsTo
    {
        return $this->belongsTo(LoanRequest::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'changed_by', 'user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before_json' => 'array',
            'after_json' => 'array',
            'changed_fields_json' => 'array',
        ];
    }
}

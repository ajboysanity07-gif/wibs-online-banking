<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanRequestDataChange extends Model
{
    /** @use HasFactory<\Database\Factories\LoanRequestDataChangeFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'loan_request_id',
        'actor_user_id',
        'field_key',
        'before_value_json',
        'after_value_json',
        'reason',
        'information_source',
        'metadata_json',
    ];

    public function loanRequest(): BelongsTo
    {
        return $this->belongsTo(LoanRequest::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'actor_user_id', 'user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before_value_json' => 'array',
            'after_value_json' => 'array',
            'metadata_json' => 'array',
        ];
    }
}

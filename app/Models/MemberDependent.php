<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberDependent extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'member_dependent_profile_id',
        'category',
        'slot',
        'name',
        'birthdate',
        'cycle_status',
        'cycle_number',
        'confirmed_cycle_status',
        'confirmed_cycle_number',
        'confirmed_by_loan_request_id',
    ];

    public function memberDependentProfile(): BelongsTo
    {
        return $this->belongsTo(MemberDependentProfile::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'cycle_number' => 'integer',
            'confirmed_cycle_number' => 'integer',
            'confirmed_by_loan_request_id' => 'integer',
        ];
    }
}

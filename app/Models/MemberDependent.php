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
        'relationship',
        'birthdate',
        'occupation',
        'cycle_status',
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
        ];
    }
}

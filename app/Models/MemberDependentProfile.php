<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemberDependentProfile extends Model
{
    /**
     * Category keys and their row caps (provisional). Fixed slots 1..cap
     * per category, mirrored by LoanRequestDataService's flat field keys
     * (dependent_{category}_{slot}_{attribute}).
     *
     * @var array<string, int>
     */
    public const CATEGORY_CAPS = [
        'child' => 3,
        'sibling' => 3,
        'parent' => 2,
        'extended' => 3,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'member_application_profile_id',
    ];

    public function memberApplicationProfile(): BelongsTo
    {
        return $this->belongsTo(MemberApplicationProfile::class);
    }

    public function dependents(): HasMany
    {
        return $this->hasMany(MemberDependent::class);
    }
}

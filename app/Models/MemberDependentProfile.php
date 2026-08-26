<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemberDependentProfile extends Model
{
    /**
     * Spouse cycle fields (New/Old + cycle number), same shape as the
     * repeatable categories but a singleton -- Spouse has no slot.
     *
     * @var list<string>
     */
    public const SPOUSE_FIELD_KEYS = [
        'dependent_spouse_cycle_status',
        'dependent_spouse_cycle_number',
    ];

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
        'spouse_cycle_status',
        'spouse_cycle_number',
        'applicant_confirmed_cycle_status',
        'applicant_confirmed_cycle_number',
        'applicant_confirmed_by_loan_request_id',
        'spouse_confirmed_cycle_status',
        'spouse_confirmed_cycle_number',
        'spouse_confirmed_by_loan_request_id',
    ];

    public function memberApplicationProfile(): BelongsTo
    {
        return $this->belongsTo(MemberApplicationProfile::class);
    }

    public function dependents(): HasMany
    {
        return $this->hasMany(MemberDependent::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'spouse_cycle_number' => 'integer',
            'applicant_confirmed_cycle_number' => 'integer',
            'applicant_confirmed_by_loan_request_id' => 'integer',
            'spouse_confirmed_cycle_number' => 'integer',
            'spouse_confirmed_by_loan_request_id' => 'integer',
        ];
    }

    /**
     * Name/birthdate field keys (dependent_{category}_{slot}_{attribute})
     * across every category, used to validate and filter the Settings >
     * Dependents tab submission. Cycle status/number are deliberately
     * excluded -- Settings no longer collects them, only the loan-request
     * wizard does (via its own field-key list and submission path).
     *
     * @return list<string>
     */
    public static function fieldKeys(): array
    {
        $keys = [];

        foreach (self::CATEGORY_CAPS as $category => $cap) {
            for ($slot = 1; $slot <= $cap; $slot++) {
                foreach (['name', 'birthdate'] as $attribute) {
                    $keys[] = "dependent_{$category}_{$slot}_{$attribute}";
                }
            }
        }

        return $keys;
    }
}

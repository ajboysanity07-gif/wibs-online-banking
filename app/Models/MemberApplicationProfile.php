<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberApplicationProfile extends Model
{
    /** @use HasFactory<\Database\Factories\MemberApplicationProfileFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'middle_name',
        'nickname',
        'birthdate',
        'birthplace',
        'age',
        'address',
        'length_of_stay',
        'housing_status',
        'civil_status',
        'educational_attainment',
        'number_of_children',
        'spouse_name',
        'spouse_age',
        'spouse_cell_no',
        'employment_type',
        'employer_business_name',
        'employer_business_address',
        'telephone_no',
        'current_position',
        'nature_of_business',
        'years_in_work_business',
        'gross_monthly_income',
        'payday',
        'profile_completed_at',
    ];

    public function appUser(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'user_id', 'user_id');
    }

    public function isComplete(): bool
    {
        return $this->profile_completed_at !== null;
    }

    public function syncCompletionStatus(): void
    {
        if ($this->hasRequiredFields()) {
            $this->profile_completed_at ??= now();

            return;
        }

        $this->profile_completed_at = null;
    }

    /**
     * @return list<string>
     */
    public static function fields(): array
    {
        return [
            'first_name',
            'last_name',
            'middle_name',
            'nickname',
            'birthdate',
            'birthplace',
            'age',
            'address',
            'length_of_stay',
            'housing_status',
            'civil_status',
            'educational_attainment',
            'number_of_children',
            'spouse_name',
            'spouse_age',
            'spouse_cell_no',
            'employment_type',
            'employer_business_name',
            'employer_business_address',
            'telephone_no',
            'current_position',
            'nature_of_business',
            'years_in_work_business',
            'gross_monthly_income',
            'payday',
        ];
    }

    /**
     * @return list<string>
     */
    public static function completionRequiredFields(): array
    {
        return [
            'first_name',
            'last_name',
            'birthdate',
            'birthplace',
            'address',
            'civil_status',
            'employment_type',
            'employer_business_name',
            'current_position',
            'gross_monthly_income',
            'payday',
        ];
    }

    private function hasRequiredFields(): bool
    {
        foreach (self::completionRequiredFields() as $field) {
            $value = $this->getAttribute($field);

            if ($value === null) {
                return false;
            }

            if (is_string($value) && trim($value) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'gross_monthly_income' => 'decimal:2',
            'profile_completed_at' => 'datetime',
        ];
    }
}

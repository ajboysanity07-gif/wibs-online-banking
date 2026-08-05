<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberCoMaker extends Model
{
    /** @use HasFactory<\Database\Factories\MemberCoMakerFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'member_application_profile_id',
        'label',
        'first_name',
        'last_name',
        'middle_name',
        'nickname',
        'birthdate',
        'birthplace',
        'birthplace_city',
        'birthplace_province',
        'address',
        'address1',
        'address_barangay',
        'address2',
        'address3',
        'address_zip',
        'length_of_stay',
        'housing_status',
        'cell_no',
        'civil_status',
        'sex',
        'educational_attainment',
        'number_of_children',
        'spouse_name',
        'spouse_age',
        'spouse_cell_no',
        'employment_type',
        'employer_business_name',
        'employer_business_address',
        'employer_business_address1',
        'employer_business_address_barangay',
        'employer_business_address2',
        'employer_business_address3',
        'employer_business_address_zip',
        'telephone_no',
        'current_position',
        'nature_of_business',
        'years_in_work_business',
        'gross_monthly_income',
        'payday',
        'last_used_at',
    ];

    public function memberApplicationProfile(): BelongsTo
    {
        return $this->belongsTo(MemberApplicationProfile::class);
    }

    /**
     * Person fields mirrored from LoanRequestPerson, shared by
     * SavedCoMakersService for filtering submitted payloads and by the
     * client-facing full-record serialization.
     *
     * @return list<string>
     */
    public static function personFields(): array
    {
        return [
            'first_name',
            'last_name',
            'middle_name',
            'nickname',
            'birthdate',
            'birthplace',
            'birthplace_city',
            'birthplace_province',
            'address',
            'address1',
            'address_barangay',
            'address2',
            'address3',
            'address_zip',
            'length_of_stay',
            'housing_status',
            'cell_no',
            'civil_status',
            'sex',
            'educational_attainment',
            'number_of_children',
            'spouse_name',
            'spouse_age',
            'spouse_cell_no',
            'employment_type',
            'employer_business_name',
            'employer_business_address',
            'employer_business_address1',
            'employer_business_address_barangay',
            'employer_business_address2',
            'employer_business_address3',
            'employer_business_address_zip',
            'telephone_no',
            'current_position',
            'nature_of_business',
            'years_in_work_business',
            'gross_monthly_income',
            'payday',
        ];
    }

    public function displayLabel(): string
    {
        $label = trim((string) $this->label);

        if ($label !== '') {
            return $label;
        }

        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'gross_monthly_income' => 'decimal:2',
            'last_used_at' => 'datetime',
        ];
    }
}

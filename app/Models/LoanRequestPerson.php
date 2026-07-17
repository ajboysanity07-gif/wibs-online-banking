<?php

namespace App\Models;

use App\LoanRequestPersonRole;
use App\Support\LocationComposer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanRequestPerson extends Model
{
    /** @use HasFactory<\Database\Factories\LoanRequestPersonFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'loan_request_id',
        'role',
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
        'address2',
        'address3',
        'length_of_stay',
        'housing_status',
        'cell_no',
        'civil_status',
        'educational_attainment',
        'number_of_children',
        'spouse_name',
        'spouse_age',
        'spouse_cell_no',
        'employment_type',
        'employer_business_name',
        'employer_business_address',
        'employer_business_address1',
        'employer_business_address2',
        'employer_business_address3',
        'telephone_no',
        'current_position',
        'nature_of_business',
        'years_in_work_business',
        'gross_monthly_income',
        'payday',
    ];

    public function loanRequest(): BelongsTo
    {
        return $this->belongsTo(LoanRequest::class);
    }

    public function composedBirthplace(): string
    {
        $composed = LocationComposer::composeBirthplace(
            $this->birthplace_city,
            $this->birthplace_province,
        );

        if ($composed !== '') {
            return $composed;
        }

        return trim((string) $this->birthplace);
    }

    public function composedAddress(): string
    {
        $composed = LocationComposer::compose(
            $this->address1,
            $this->address2,
            $this->address3,
        );

        if ($composed !== '') {
            return $composed;
        }

        return trim((string) $this->address);
    }

    public function composedEmployerBusinessAddress(): string
    {
        $composed = LocationComposer::compose(
            $this->employer_business_address1,
            $this->employer_business_address2,
            $this->employer_business_address3,
        );

        if ($composed !== '') {
            return $composed;
        }

        return trim((string) $this->employer_business_address);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'gross_monthly_income' => 'decimal:2',
            'role' => LoanRequestPersonRole::class,
        ];
    }
}

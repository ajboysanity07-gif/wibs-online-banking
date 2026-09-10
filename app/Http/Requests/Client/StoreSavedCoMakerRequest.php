<?php

namespace App\Http\Requests\Client;

use App\Models\AppUser;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSavedCoMakerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof AppUser
            && ! $user->isAdminOnly()
            && $user->hasMemberAccess();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Deliberately lenient (only name required) -- this saves a reusable
     * contact for later, not the full loan-request submission, so it
     * shouldn't block on the stricter per-field rules (PSGC locations,
     * payday enum, etc.) enforced by LoanRequestStoreRequest.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:255'],
            'saved_co_maker_id' => ['nullable', 'integer'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:255'],
            'birthdate' => ['nullable', 'date'],
            'birthplace' => ['nullable', 'string', 'max:255'],
            'birthplace_city' => ['nullable', 'string', 'max:255'],
            'birthplace_province' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'address1' => ['nullable', 'string', 'max:255'],
            'address_barangay' => ['nullable', 'string', 'max:255'],
            'address2' => ['nullable', 'string', 'max:255'],
            'address3' => ['nullable', 'string', 'max:255'],
            'address_zip' => ['nullable', 'string', 'max:20'],
            'length_of_stay' => ['nullable', 'string', 'max:255'],
            'housing_status' => ['nullable', 'string', 'max:255'],
            'cell_no' => ['nullable', 'string', 'max:20'],
            'civil_status' => ['nullable', 'string', 'max:255'],
            'sex' => ['nullable', 'string', 'max:255'],
            'educational_attainment' => ['nullable', 'string', 'max:255'],
            'number_of_children' => ['nullable', 'integer', 'min:0'],
            'spouse_name' => ['nullable', 'string', 'max:255'],
            'spouse_age' => ['nullable', 'integer', 'min:0'],
            'spouse_cell_no' => ['nullable', 'string', 'max:20'],
            'employment_type' => ['nullable', 'string', 'max:255'],
            'employer_business_name' => ['nullable', 'string', 'max:255'],
            'employer_business_address' => ['nullable', 'string', 'max:255'],
            'employer_business_address1' => ['nullable', 'string', 'max:255'],
            'employer_business_address_barangay' => ['nullable', 'string', 'max:255'],
            'employer_business_address2' => ['nullable', 'string', 'max:255'],
            'employer_business_address3' => ['nullable', 'string', 'max:255'],
            'employer_business_address_zip' => ['nullable', 'string', 'max:20'],
            'telephone_no' => ['nullable', 'string', 'max:20'],
            'current_position' => ['nullable', 'string', 'max:255'],
            'nature_of_business' => ['nullable', 'string', 'max:255'],
            'years_in_work_business' => ['nullable', 'string', 'max:255'],
            'gross_monthly_income' => ['nullable', 'numeric', 'min:0'],
            'payday' => ['nullable', 'string', 'max:255'],
        ];
    }
}

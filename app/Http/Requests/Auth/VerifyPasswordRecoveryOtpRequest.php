<?php

namespace App\Http\Requests\Auth;

use App\Services\Auth\PasswordRecoveryService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class VerifyPasswordRecoveryOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'digits:'.PasswordRecoveryService::OTP_LENGTH],
        ];
    }
}

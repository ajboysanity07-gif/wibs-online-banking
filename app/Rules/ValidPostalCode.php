<?php

namespace App\Rules;

use App\Services\Locations\ZipCodeService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPostalCode implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! app(ZipCodeService::class)->isKnownZip($value)) {
            $fail('The :attribute must be a valid postal code resolved from the selected city.');
        }
    }
}

<?php

namespace App\Rules;

use App\Services\Locations\PsgcService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPsgcProvince implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! app(PsgcService::class)->isKnownProvince($value)) {
            $fail('The :attribute must be selected from the province suggestions.');
        }
    }
}

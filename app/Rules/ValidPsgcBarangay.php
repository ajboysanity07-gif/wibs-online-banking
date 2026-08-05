<?php

namespace App\Rules;

use App\Services\Locations\PsgcService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPsgcBarangay implements ValidationRule
{
    public function __construct(
        private readonly ?string $municipality,
        private readonly ?string $province = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be selected from the barangay suggestions.');

            return;
        }

        $municipality = trim((string) $this->municipality);

        if ($municipality === '') {
            $fail('The :attribute requires a selected city/municipality.');

            return;
        }

        if (! app(PsgcService::class)->isKnownBarangay($municipality, $value, $this->province)) {
            $fail('The :attribute must be selected from the barangay suggestions.');
        }
    }
}

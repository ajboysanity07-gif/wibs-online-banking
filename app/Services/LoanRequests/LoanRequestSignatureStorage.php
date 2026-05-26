<?php

namespace App\Services\LoanRequests;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LoanRequestSignatureStorage
{
    public function storeBase64Png(?string $data): ?string
    {
        if (! is_string($data) || ! str_starts_with($data, 'data:image/png;base64,')) {
            return null;
        }

        $encoded = substr($data, strlen('data:image/png;base64,'));
        $image = base64_decode($encoded, true);

        if ($image === false) {
            return null;
        }

        $path = 'loan-requests/signatures/'.Str::uuid().'.png';
        Storage::disk('public')->put($path, $image);

        return $path;
    }

    public function delete(?string $path): void
    {
        $normalizedPath = is_string($path) ? trim($path) : '';

        if (
            $normalizedPath === ''
            || ! Storage::disk('public')->exists($normalizedPath)
        ) {
            return;
        }

        Storage::disk('public')->delete($normalizedPath);
    }
}

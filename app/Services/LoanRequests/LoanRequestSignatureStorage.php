<?php

namespace App\Services\LoanRequests;

use App\Services\SignaturePngService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LoanRequestSignatureStorage
{
    public function __construct(
        private SignaturePngService $signaturePngService,
    ) {}

    public function storeBase64Png(?string $data): ?string
    {
        if (! is_string($data)) {
            return null;
        }

        $image = $this->signaturePngService->normalizeBase64Png($data);

        if (! is_string($image) || $image === '') {
            return null;
        }

        $path = 'loan-requests/signatures/'.Str::uuid().'.png';
        Storage::disk('public')->put($path, $image, 'public');

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

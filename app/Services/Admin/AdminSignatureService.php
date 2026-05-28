<?php

namespace App\Services\Admin;

use App\Models\AdminSignature;
use App\Models\AppUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AdminSignatureService
{
    public function saveForUser(
        AppUser $user,
        string $signatureData,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): AdminSignature {
        $storedPath = $this->storeBase64Png($user, $signatureData);

        try {
            return DB::transaction(function () use (
                $user,
                $storedPath,
                $ipAddress,
                $userAgent,
            ): AdminSignature {
                AdminSignature::query()
                    ->where('user_id', $user->user_id)
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'updated_at' => now(),
                    ]);

                /** @var AdminSignature $signature */
                $signature = AdminSignature::query()->create([
                    'user_id' => $user->user_id,
                    'signature_path' => $storedPath,
                    'is_active' => true,
                    'created_ip' => $this->blank($ipAddress),
                    'created_user_agent' => $this->blank($userAgent),
                ]);

                return $signature;
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($storedPath);

            throw $exception;
        }
    }

    private function storeBase64Png(AppUser $user, string $signatureData): string
    {
        if (! str_starts_with($signatureData, 'data:image/png;base64,')) {
            throw new RuntimeException('Loan manager signature must be a PNG data URL.');
        }

        $encoded = substr($signatureData, strlen('data:image/png;base64,'));
        $decoded = base64_decode($encoded, true);

        if ($decoded === false || $decoded === '') {
            throw new RuntimeException('Loan manager signature could not be decoded.');
        }

        $path = sprintf(
            'loan-manager-signatures/%d/%s.png',
            $user->user_id,
            Str::uuid(),
        );

        Storage::disk('public')->put($path, $decoded);

        return $path;
    }

    private function blank(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}

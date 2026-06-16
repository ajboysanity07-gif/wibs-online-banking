<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SemaphoreSmsService
{
    public function send(string $phoneNumber, string $message): bool
    {
        return $this->sendDetailed($phoneNumber, $message)['status'] === 'sent';
    }

    /**
     * @return array{
     *     status:'sent'|'temporary_failure'|'permanent_failure'|'skipped',
     *     provider_reference:string|null,
     *     provider_error:string|null
     * }
     */
    public function sendDetailed(string $phoneNumber, string $message): array
    {
        $apiKey = config('services.semaphore.api_key');
        $baseUrl = config('services.semaphore.base_url');
        $senderName = config('services.semaphore.sender_name');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            Log::warning('Semaphore SMS API key is not configured.');

            return [
                'status' => 'skipped',
                'provider_reference' => null,
                'provider_error' => 'SMS provider API key is not configured.',
            ];
        }

        if (! is_string($baseUrl) || trim($baseUrl) === '') {
            Log::warning('Semaphore SMS base URL is not configured.');

            return [
                'status' => 'skipped',
                'provider_reference' => null,
                'provider_error' => 'SMS provider base URL is not configured.',
            ];
        }

        $payload = [
            'apikey' => $apiKey,
            'number' => $phoneNumber,
            'message' => $message,
        ];

        if (is_string($senderName) && trim($senderName) !== '') {
            $payload['sendername'] = $senderName;
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post($baseUrl, $payload);

            if ($response->failed()) {
                $providerError = $this->sanitizeProviderError(
                    $response->body(),
                );
                Log::warning('Semaphore SMS request failed.', [
                    'status' => $response->status(),
                    'body' => $providerError,
                ]);

                return [
                    'status' => $response->status() >= 500
                        ? 'temporary_failure'
                        : 'permanent_failure',
                    'provider_reference' => null,
                    'provider_error' => $providerError,
                ];
            }
        } catch (\Throwable $exception) {
            $providerError = $this->sanitizeProviderError(
                $exception->getMessage(),
            );
            Log::warning('Semaphore SMS request error.', [
                'error' => $providerError,
            ]);

            return [
                'status' => 'temporary_failure',
                'provider_reference' => null,
                'provider_error' => $providerError,
            ];
        }

        $responseBody = $response->json();
        $providerReference = null;

        if (is_array($responseBody)) {
            $firstItem = $responseBody[0] ?? null;

            if (is_array($firstItem)) {
                $reference = $firstItem['message_id']
                    ?? $firstItem['id']
                    ?? null;

                if (is_scalar($reference)) {
                    $providerReference = trim((string) $reference) !== ''
                        ? trim((string) $reference)
                        : null;
                }
            }
        }

        return [
            'status' => 'sent',
            'provider_reference' => $providerReference,
            'provider_error' => null,
        ];
    }

    private function sanitizeProviderError(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = preg_replace('/\s+/', ' ', trim($value));

        if (! is_string($normalized) || $normalized === '') {
            return null;
        }

        return mb_substr($normalized, 0, 240);
    }
}

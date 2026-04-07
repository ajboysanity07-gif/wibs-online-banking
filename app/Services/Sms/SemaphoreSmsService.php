<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SemaphoreSmsService
{
    public function send(string $phoneNumber, string $message): bool
    {
        $apiKey = config('services.semaphore.api_key');
        $baseUrl = config('services.semaphore.base_url');
        $senderName = config('services.semaphore.sender_name');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            Log::warning('Semaphore SMS API key is not configured.');

            return false;
        }

        if (! is_string($baseUrl) || trim($baseUrl) === '') {
            Log::warning('Semaphore SMS base URL is not configured.');

            return false;
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
                Log::warning('Semaphore SMS request failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }
        } catch (\Throwable $exception) {
            Log::warning('Semaphore SMS request error.', [
                'error' => $exception->getMessage(),
            ]);

            return false;
        }

        return true;
    }
}

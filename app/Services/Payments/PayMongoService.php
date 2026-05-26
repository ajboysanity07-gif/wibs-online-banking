<?php

namespace App\Services\Payments;

use App\Models\OnlinePayment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PayMongoService
{
    /**
     * @var list<string>
     */
    private const SUPPORTED_PAYMENT_METHODS = ['gcash', 'paymaya', 'qrph', 'dob'];

    /**
     * Create a PayMongo Hosted Checkout session.
     *
     * @return array<string, mixed>
     */
    public function createCheckoutSession(
        OnlinePayment $payment,
        string $successUrl,
        string $cancelUrl,
    ): array {
        $paymentMethods = $this->paymentMethods();

        $response = Http::withBasicAuth($this->secretKey(), '')
            ->acceptJson()
            ->asJson()
            ->post($this->baseUrl().'/checkout_sessions', [
                'data' => [
                    'attributes' => [
                        'billing' => [
                            'name' => $payment->user?->name,
                            'email' => $payment->user?->email,
                            'phone' => $payment->user?->phoneno,
                        ],
                        'description' => 'WIBS loan payment for '.$payment->loan_number,
                        'line_items' => [
                            [
                                'amount' => $payment->amount,
                                'currency' => $payment->currency,
                                'description' => 'Loan payment for '.$payment->loan_number,
                                'name' => 'WIBS Loan Payment',
                                'quantity' => 1,
                            ],
                        ],
                        'metadata' => [
                            'online_payment_id' => (string) $payment->id,
                            'loan_number' => $payment->loan_number,
                            'acctno' => $payment->acctno,
                            'user_id' => $payment->user_id === null
                                ? null
                                : (string) $payment->user_id,
                        ],
                        'payment_method_types' => $paymentMethods,
                        'send_email_receipt' => false,
                        'show_description' => true,
                        'show_line_items' => true,
                        'success_url' => $successUrl,
                        'cancel_url' => $cancelUrl,
                    ],
                ],
            ]);

        if ($response->failed()) {
            Log::warning('PayMongo checkout request failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
                'payment_methods' => $paymentMethods,
            ]);
        }

        $response = $response->throw()->json();

        if (! is_array($response)) {
            throw new RuntimeException('PayMongo returned an invalid checkout response.');
        }

        return $response;
    }

    public function verifyWebhookSignature(
        string $payload,
        ?string $signatureHeader,
    ): bool {
        $secret = config('paymongo.webhook_secret');

        if (
            ! is_string($secret) ||
            trim($secret) === '' ||
            ! is_string($signatureHeader) ||
            trim($signatureHeader) === ''
        ) {
            return false;
        }

        $parts = $this->parseSignatureHeader($signatureHeader);
        $timestamp = $parts['t'] ?? null;
        $signatureKey = $this->mode() === 'live' ? 'li' : 'te';
        $signature = $parts[$signatureKey] ?? null;

        if (! is_string($timestamp) || $timestamp === '' || ! is_string($signature) || $signature === '') {
            return false;
        }

        $expectedSignature = hash_hmac(
            'sha256',
            $timestamp.'.'.$payload,
            trim($secret),
        );

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhook(array $payload): void
    {
        $eventType = data_get($payload, 'data.attributes.type');

        if (! is_string($eventType)) {
            return;
        }

        if (! in_array($eventType, [
            'checkout_session.payment.paid',
            'payment.paid',
            'payment.failed',
        ], true)) {
            return;
        }

        $payment = $this->resolveOnlinePayment($payload);

        if ($payment === null) {
            return;
        }

        if ($eventType === 'payment.failed') {
            $this->markFailed($payment, $payload);

            return;
        }

        $this->markPaid($payment, $payload);
    }

    /**
     * @return array<string, string>
     */
    private function parseSignatureHeader(string $signatureHeader): array
    {
        $parts = [];

        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

            if (is_string($key) && $key !== '' && is_string($value)) {
                $parts[$key] = $value;
            }
        }

        return $parts;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveOnlinePayment(array $payload): ?OnlinePayment
    {
        $metadata = $this->metadataFromPayload($payload);
        $onlinePaymentId = $metadata['online_payment_id'] ?? null;

        if (is_numeric($onlinePaymentId)) {
            $payment = OnlinePayment::query()->find((int) $onlinePaymentId);

            if ($payment !== null) {
                return $payment;
            }
        }

        $checkoutId = $this->checkoutIdFromPayload($payload);

        if ($checkoutId !== null) {
            $payment = OnlinePayment::query()
                ->where('provider_checkout_id', $checkoutId)
                ->first();

            if ($payment !== null) {
                return $payment;
            }
        }

        $paymentId = $this->providerPaymentIdFromPayload($payload);

        if ($paymentId !== null) {
            return OnlinePayment::query()
                ->where('provider_payment_id', $paymentId)
                ->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function metadataFromPayload(array $payload): array
    {
        foreach ([
            'data.attributes.data.attributes.metadata',
            'data.attributes.data.attributes.payment_intent.attributes.metadata',
            'data.attributes.data.attributes.payments.0.attributes.metadata',
        ] as $path) {
            $metadata = data_get($payload, $path);

            if (is_array($metadata)) {
                return $metadata;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function checkoutIdFromPayload(array $payload): ?string
    {
        $eventDataType = data_get($payload, 'data.attributes.data.type');
        $eventDataId = data_get($payload, 'data.attributes.data.id');

        if ($eventDataType === 'checkout_session' && is_string($eventDataId)) {
            return $eventDataId;
        }

        $checkoutId = data_get($payload, 'data.attributes.data.attributes.checkout_session_id');

        return is_string($checkoutId) && $checkoutId !== '' ? $checkoutId : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function providerPaymentIdFromPayload(array $payload): ?string
    {
        foreach ([
            'data.attributes.data.attributes.payments.0.id',
            'data.attributes.data.attributes.payment_intent.attributes.payments.0.id',
            'data.attributes.data.id',
        ] as $path) {
            $paymentId = data_get($payload, $path);

            if (is_string($paymentId) && str_starts_with($paymentId, 'pay_')) {
                return $paymentId;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function referenceNumberFromPayload(array $payload): ?string
    {
        foreach ([
            'data.attributes.data.attributes.reference_number',
            'data.attributes.data.attributes.external_reference_number',
            'data.attributes.data.attributes.payments.0.attributes.external_reference_number',
            'data.attributes.data.attributes.payment_intent.attributes.payments.0.attributes.external_reference_number',
        ] as $path) {
            $reference = data_get($payload, $path);

            if (is_string($reference) && $reference !== '') {
                return $reference;
            }
        }

        return $this->providerPaymentIdFromPayload($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function markPaid(OnlinePayment $payment, array $payload): void
    {
        $attributes = [
            'provider_payment_id' => $payment->provider_payment_id
                ?? $this->providerPaymentIdFromPayload($payload),
            'reference_number' => $payment->reference_number
                ?? $this->referenceNumberFromPayload($payload),
            'paid_at' => $payment->paid_at ?? now(),
            'raw_payload' => $payload,
        ];

        if ($payment->status !== OnlinePayment::STATUS_POSTED) {
            $attributes['status'] = OnlinePayment::STATUS_PAID;
        }

        $payment->fill($attributes);
        $payment->save();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function markFailed(OnlinePayment $payment, array $payload): void
    {
        $attributes = [
            'provider_payment_id' => $payment->provider_payment_id
                ?? $this->providerPaymentIdFromPayload($payload),
            'reference_number' => $payment->reference_number
                ?? $this->referenceNumberFromPayload($payload),
            'raw_payload' => $payload,
        ];

        if (! in_array($payment->status, [
            OnlinePayment::STATUS_PAID,
            OnlinePayment::STATUS_POSTED,
        ], true)) {
            $attributes['status'] = OnlinePayment::STATUS_FAILED;
        }

        $payment->fill($attributes);
        $payment->save();
    }

    private function secretKey(): string
    {
        $secretKey = config('paymongo.secret_key');

        if (! is_string($secretKey) || trim($secretKey) === '') {
            throw new RuntimeException('PayMongo secret key is not configured.');
        }

        return trim($secretKey);
    }

    private function baseUrl(): string
    {
        $baseUrl = config('paymongo.base_url', 'https://api.paymongo.com/v1');

        if (! is_string($baseUrl) || trim($baseUrl) === '') {
            return 'https://api.paymongo.com/v1';
        }

        return rtrim($baseUrl, '/');
    }

    /**
     * @return list<string>
     */
    private function paymentMethods(): array
    {
        $paymentMethods = config('paymongo.payment_methods');

        if (! is_array($paymentMethods)) {
            return ['gcash'];
        }

        $paymentMethods = array_values(array_filter(array_map(
            static fn (mixed $method): ?string => is_string($method) ? strtolower(trim($method)) : null,
            $paymentMethods,
        )));

        $paymentMethods = array_values(array_intersect($paymentMethods, self::SUPPORTED_PAYMENT_METHODS));

        return $paymentMethods === [] ? ['gcash'] : $paymentMethods;
    }

    private function mode(): string
    {
        return config('paymongo.mode') === 'live' ? 'live' : 'test';
    }
}

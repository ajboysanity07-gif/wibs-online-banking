<?php

namespace App\Services\Payments;

use App\Models\AppUser;
use App\Models\PaymongoLoanPayment;
use App\Models\Wlnmaster;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class PaymongoCheckoutService
{
    public function __construct(
        private PaymongoServiceFeeCalculator $calculator,
    ) {}

    /**
     * @return array{
     *     payment: \App\Models\PaymongoLoanPayment,
     *     checkout_url: string,
     *     amounts: array{
     *         method: string,
     *         label: string,
     *         base_amount_cents: int,
     *         service_fee_cents: int,
     *         gross_amount_cents: int,
     *         rate: float,
     *         fixed_fee_cents: int
     *     }
     * }
     *
     * @throws \Illuminate\Http\Client\ConnectionException
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function create(
        AppUser $user,
        Wlnmaster $loan,
        string $acctno,
        int $baseAmountCents,
        string $method,
    ): array {
        $secretKey = $this->secretKey();
        $amounts = $this->calculator->calculate($baseAmountCents, $method);
        $paymentMethodType = $this->calculator->paymongoPaymentMethodType(
            $amounts['method'],
        );

        $payment = PaymongoLoanPayment::query()->create([
            'user_id' => $user->getKey(),
            'acctno' => $acctno,
            'loan_number' => $loan->lnnumber,
            'currency' => 'PHP',
            'payment_method' => $amounts['method'],
            'payment_method_label' => $amounts['label'],
            'payment_method_type' => $paymentMethodType,
            'base_amount_cents' => $amounts['base_amount_cents'],
            'service_fee_cents' => $amounts['service_fee_cents'],
            'gross_amount_cents' => $amounts['gross_amount_cents'],
            'status' => PaymongoLoanPayment::STATUS_PENDING,
            'provider' => 'paymongo',
            'metadata' => [
                'fee_calculation' => $amounts,
            ],
        ]);

        $payload = $this->checkoutPayload(
            $payment,
            $user,
            $loan,
            $amounts,
            $paymentMethodType,
        );

        try {
            $response = Http::withBasicAuth($secretKey, '')
                ->acceptJson()
                ->asJson()
                ->post($this->endpoint('/checkout_sessions'), $payload);

            $response->throw();
        } catch (ConnectionException|RequestException $exception) {
            $this->markAsFailed($payment, $exception);

            throw $exception;
        }

        $responseData = $response->json();
        $checkoutUrl = data_get($responseData, 'data.attributes.checkout_url');

        if (! is_string($checkoutUrl) || trim($checkoutUrl) === '') {
            $this->markAsFailed(
                $payment,
                new RuntimeException('PayMongo did not return a checkout URL.'),
                $responseData,
            );

            throw new RuntimeException('PayMongo did not return a checkout URL.');
        }

        $payment->forceFill([
            'provider_checkout_session_id' => data_get($responseData, 'data.id'),
            'provider_payment_intent_id' => $this->paymentIntentId($responseData),
            'provider_reference_number' => data_get($responseData, 'data.attributes.reference_number'),
            'checkout_url' => $checkoutUrl,
            'expires_at' => $this->parsePaymongoTimestamp(
                data_get($responseData, 'data.attributes.expires_at'),
            ),
            'metadata' => $this->mergeMetadata($payment, [
                'checkout_session' => [
                    'id' => data_get($responseData, 'data.id'),
                    'status' => data_get($responseData, 'data.attributes.status'),
                    'livemode' => data_get($responseData, 'data.attributes.livemode'),
                    'payment_method_types' => data_get($responseData, 'data.attributes.payment_method_types'),
                    'reference_number' => data_get($responseData, 'data.attributes.reference_number'),
                ],
                'payment_intent_id' => $this->paymentIntentId($responseData),
            ]),
        ])->save();

        return [
            'payment' => $payment->refresh(),
            'checkout_url' => $checkoutUrl,
            'amounts' => $amounts,
        ];
    }

    private function secretKey(): string
    {
        $secretKey = config('services.paymongo.secret_key');

        if (! is_string($secretKey) || trim($secretKey) === '') {
            throw new RuntimeException('PayMongo secret key is not configured.');
        }

        return trim($secretKey);
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('services.paymongo.base_url'), '/').$path;
    }

    /**
     * @param array{
     *     method: string,
     *     label: string,
     *     base_amount_cents: int,
     *     service_fee_cents: int,
     *     gross_amount_cents: int,
     *     rate: float,
     *     fixed_fee_cents: int
     * } $amounts
     * @return array<string, mixed>
     */
    private function checkoutPayload(
        PaymongoLoanPayment $payment,
        AppUser $user,
        Wlnmaster $loan,
        array $amounts,
        string $paymentMethodType,
    ): array {
        $lineItems = [
            [
                'currency' => 'PHP',
                'amount' => $amounts['base_amount_cents'],
                'name' => 'Loan Payment',
                'quantity' => 1,
                'description' => 'Loan '.$loan->lnnumber,
            ],
        ];

        if ($amounts['service_fee_cents'] > 0) {
            $lineItems[] = [
                'currency' => 'PHP',
                'amount' => $amounts['service_fee_cents'],
                'name' => 'Service Fee',
                'quantity' => 1,
                'description' => $amounts['label'].' PayMongo processing fee',
            ];
        }

        return [
            'data' => [
                'attributes' => [
                    'send_email_receipt' => false,
                    'show_description' => true,
                    'show_line_items' => true,
                    'description' => 'Loan payment for loan '.$loan->lnnumber,
                    'line_items' => $lineItems,
                    'payment_method_types' => [$paymentMethodType],
                    'success_url' => $this->configuredUrl('success_url', $payment),
                    'cancel_url' => $this->configuredUrl('cancel_url', $payment),
                    'metadata' => $this->paymongoMetadata(
                        $payment,
                        $user,
                        $loan,
                        $amounts,
                    ),
                ],
            ],
        ];
    }

    private function configuredUrl(
        string $key,
        PaymongoLoanPayment $payment,
    ): string {
        $template = config('services.paymongo.'.$key);

        if (! is_string($template) || trim($template) === '') {
            $routeName = $key === 'cancel_url'
                ? 'client.loan-payments.paymongo.cancel'
                : 'client.loan-payments.paymongo.success';

            return route($routeName, ['payment' => $payment]);
        }

        return str_replace('{payment}', (string) $payment->getKey(), $template);
    }

    /**
     * @param array{
     *     method: string,
     *     base_amount_cents: int,
     *     service_fee_cents: int,
     *     gross_amount_cents: int
     * } $amounts
     * @return array<string, string>
     */
    private function paymongoMetadata(
        PaymongoLoanPayment $payment,
        AppUser $user,
        Wlnmaster $loan,
        array $amounts,
    ): array {
        return [
            'local_payment_id' => (string) $payment->getKey(),
            'user_id' => (string) $user->getKey(),
            'acctno' => $payment->acctno,
            'loan_number' => (string) $loan->lnnumber,
            'base_amount_cents' => (string) $amounts['base_amount_cents'],
            'service_fee_cents' => (string) $amounts['service_fee_cents'],
            'gross_amount_cents' => (string) $amounts['gross_amount_cents'],
            'payment_method' => $amounts['method'],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $responseData
     */
    private function markAsFailed(
        PaymongoLoanPayment $payment,
        Throwable $exception,
        ?array $responseData = null,
    ): void {
        $payment->forceFill([
            'status' => PaymongoLoanPayment::STATUS_FAILED,
            'metadata' => $this->mergeMetadata($payment, [
                'checkout_error' => [
                    'class' => $exception::class,
                    'message' => $exception->getMessage(),
                    'response' => $responseData,
                ],
            ]),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function mergeMetadata(
        PaymongoLoanPayment $payment,
        array $metadata,
    ): array {
        return array_replace_recursive($payment->metadata ?? [], $metadata);
    }

    /**
     * @param  array<string, mixed>  $responseData
     */
    private function paymentIntentId(array $responseData): ?string
    {
        $paymentIntentId = data_get($responseData, 'data.attributes.payment_intent.id')
            ?? data_get($responseData, 'data.attributes.payment_intent_id');

        return is_string($paymentIntentId) && $paymentIntentId !== ''
            ? $paymentIntentId
            : null;
    }

    private function parsePaymongoTimestamp(mixed $value): ?Carbon
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return Carbon::createFromTimestamp((int) $value);
        }

        if (is_string($value) && trim($value) !== '') {
            return Carbon::parse($value);
        }

        return null;
    }
}

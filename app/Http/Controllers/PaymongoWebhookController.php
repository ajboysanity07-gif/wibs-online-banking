<?php

namespace App\Http\Controllers;

use App\Models\PaymongoLoanPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class PaymongoWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $event = json_decode($payload, true);

        if (! is_array($event)) {
            return response()->json(['ok' => false], 400);
        }

        if (! $this->hasValidSignature($request, $payload, $event)) {
            if (! app()->environment(['local', 'testing'])) {
                return response()->json(['ok' => false], 400);
            }

            Log::warning('PayMongo webhook signature verification bypassed.', [
                'event_id' => data_get($event, 'data.id'),
                'event_type' => data_get($event, 'data.attributes.type'),
                'environment' => app()->environment(),
            ]);
        }

        $eventType = data_get($event, 'data.attributes.type');
        $resource = data_get($event, 'data.attributes.data');

        if (! is_string($eventType) || ! is_array($resource)) {
            Log::warning('PayMongo webhook missing event type or data.', [
                'event_id' => data_get($event, 'data.id'),
            ]);

            return response()->json(['ok' => true]);
        }

        $payment = $this->findPayment($resource, $event);

        if ($payment === null) {
            Log::warning('PayMongo webhook could not be matched to a local payment.', [
                'event_id' => data_get($event, 'data.id'),
                'event_type' => $eventType,
                'resource_id' => data_get($resource, 'id'),
                'payment_intent_id' => $this->paymentIntentId($resource),
            ]);

            return response()->json(['ok' => true]);
        }

        $this->updatePayment($payment, $eventType, $resource, $event);

        return response()->json(['ok' => true]);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function hasValidSignature(
        Request $request,
        string $payload,
        array $event,
    ): bool {
        $secret = config('services.paymongo.webhook_secret');

        if (! is_string($secret) || trim($secret) === '') {
            return false;
        }

        $header = $request->headers->get('Paymongo-Signature')
            ?? $request->headers->get('X-Paymongo-Signature');

        if (! is_string($header) || trim($header) === '') {
            return false;
        }

        $parts = $this->signatureParts($header);
        $timestamp = $parts['t'] ?? null;

        if (! is_string($timestamp) || trim($timestamp) === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, trim($secret));
        $signatureKey = data_get($event, 'data.attributes.livemode') === true
            ? 'li'
            : 'te';
        $provided = $parts[$signatureKey]
            ?? $parts['te']
            ?? $parts['li']
            ?? null;

        return is_string($provided)
            && $provided !== ''
            && hash_equals($expected, $provided);
    }

    /**
     * @return array<string, string>
     */
    private function signatureParts(string $header): array
    {
        $parts = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($key !== '') {
                $parts[$key] = $value;
            }
        }

        return $parts;
    }

    /**
     * @param  array<string, mixed>  $resource
     * @param  array<string, mixed>  $event
     */
    private function findPayment(
        array $resource,
        array $event,
    ): ?PaymongoLoanPayment {
        $localPaymentId = $this->firstString([
            data_get($resource, 'attributes.metadata.local_payment_id'),
            data_get($resource, 'attributes.payment_intent.attributes.metadata.local_payment_id'),
            data_get($resource, 'attributes.payment_intent.metadata.local_payment_id'),
            data_get($event, 'data.attributes.data.attributes.metadata.local_payment_id'),
        ]);

        if ($localPaymentId !== null) {
            $payment = PaymongoLoanPayment::query()->find($localPaymentId);

            if ($payment !== null) {
                return $payment;
            }
        }

        $checkoutSessionId = $this->checkoutSessionId($resource);

        if ($checkoutSessionId !== null) {
            $payment = PaymongoLoanPayment::query()
                ->where('provider_checkout_session_id', $checkoutSessionId)
                ->first();

            if ($payment !== null) {
                return $payment;
            }
        }

        $paymentIntentId = $this->paymentIntentId($resource);

        if ($paymentIntentId === null) {
            return null;
        }

        return PaymongoLoanPayment::query()
            ->where('provider_payment_intent_id', $paymentIntentId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $resource
     * @param  array<string, mixed>  $event
     */
    private function updatePayment(
        PaymongoLoanPayment $payment,
        string $eventType,
        array $resource,
        array $event,
    ): void {
        $targetStatus = $this->statusForEvent($eventType);
        $paymentIntentId = $this->paymentIntentId($resource);
        $referenceNumber = $this->referenceNumber($resource);
        $metadata = $this->mergeWebhookMetadata(
            $payment,
            $eventType,
            $resource,
            $event,
        );

        $updates = [
            'metadata' => $metadata,
        ];

        if ($paymentIntentId !== null) {
            $updates['provider_payment_intent_id'] = $paymentIntentId;
        }

        if ($referenceNumber !== null) {
            $updates['provider_reference_number'] = $referenceNumber;
        }

        if ($targetStatus !== null) {
            if (
                $payment->status !== PaymongoLoanPayment::STATUS_PAID
                || $targetStatus === PaymongoLoanPayment::STATUS_PAID
            ) {
                $updates['status'] = $targetStatus;
            }

            if ($targetStatus === PaymongoLoanPayment::STATUS_PAID) {
                $updates['paid_at'] = $payment->paid_at
                    ?? $this->paidAt($resource)
                    ?? now();
            }
        }

        $payment->forceFill($updates)->save();
    }

    private function statusForEvent(string $eventType): ?string
    {
        return match ($eventType) {
            'checkout_session.payment.paid',
            'link.payment.paid',
            'payment.paid',
            'payment_intent.succeeded' => PaymongoLoanPayment::STATUS_PAID,
            'payment.failed',
            'payment_intent.payment_failed' => PaymongoLoanPayment::STATUS_FAILED,
            'checkout_session.expired',
            'qrph.expired',
            'source.expired' => PaymongoLoanPayment::STATUS_EXPIRED,
            'checkout_session.cancelled',
            'source.cancelled' => PaymongoLoanPayment::STATUS_CANCELLED,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $resource
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function mergeWebhookMetadata(
        PaymongoLoanPayment $payment,
        string $eventType,
        array $resource,
        array $event,
    ): array {
        $metadata = $payment->metadata ?? [];
        $eventIds = $metadata['webhook_event_ids'] ?? [];

        if (! is_array($eventIds)) {
            $eventIds = [];
        }

        $eventId = data_get($event, 'data.id');

        if (is_string($eventId) && $eventId !== '') {
            $eventIds[] = $eventId;
        }

        return array_replace_recursive($metadata, [
            'webhook_event_ids' => array_values(array_unique($eventIds)),
            'last_webhook' => [
                'event_id' => $eventId,
                'event_type' => $eventType,
                'resource_id' => data_get($resource, 'id'),
                'resource_type' => data_get($resource, 'type'),
                'status' => data_get($resource, 'attributes.status'),
                'payment_intent_id' => $this->paymentIntentId($resource),
                'reference_number' => $this->referenceNumber($resource),
                'received_at' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    private function checkoutSessionId(array $resource): ?string
    {
        if (data_get($resource, 'type') === 'checkout_session') {
            return $this->firstString([data_get($resource, 'id')]);
        }

        return $this->firstString([
            data_get($resource, 'attributes.checkout_session_id'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    private function paymentIntentId(array $resource): ?string
    {
        if (data_get($resource, 'type') === 'payment_intent') {
            return $this->firstString([data_get($resource, 'id')]);
        }

        return $this->firstString([
            data_get($resource, 'attributes.payment_intent_id'),
            data_get($resource, 'attributes.payment_intent.id'),
            data_get($resource, 'attributes.payment_intent.data.id'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    private function referenceNumber(array $resource): ?string
    {
        return $this->firstString([
            data_get($resource, 'attributes.reference_number'),
            data_get($resource, 'attributes.external_reference_number'),
            data_get($resource, 'attributes.metadata.pm_reference_number'),
            data_get($resource, 'attributes.payment_intent.attributes.metadata.pm_reference_number'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    private function paidAt(array $resource): ?Carbon
    {
        return $this->parsePaymongoTimestamp($this->firstValue([
            data_get($resource, 'attributes.paid_at'),
            data_get($resource, 'attributes.payments.0.attributes.paid_at'),
            data_get($resource, 'attributes.payment_intent.attributes.payments.0.attributes.paid_at'),
        ]));
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function firstString(array $values): ?string
    {
        $value = $this->firstValue($values);

        return is_string($value) && trim($value) !== ''
            ? trim($value)
            : null;
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function firstValue(array $values): mixed
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
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

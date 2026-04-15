<?php

namespace App\Http\Resources\Spa;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Log;
use JsonSerializable;
use Stringable;
use Throwable;

class NotificationResource extends JsonResource
{
    private const FALLBACK_TYPE = 'notification_unavailable';

    private const FALLBACK_TITLE = 'Notification unavailable';

    private const FALLBACK_MESSAGE = 'This notification could not be displayed.';

    /**
     * @return array{
     *     id: string,
     *     data: array<string, mixed>,
     *     read_at: string|null,
     *     created_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->getKey(),
            'data' => $this->resolvePayload(),
            'read_at' => $this->formatDateValue(
                $this->resource->getRawOriginal('read_at'),
                'read_at',
            ),
            'created_at' => $this->formatDateValue(
                $this->resource->getRawOriginal('created_at'),
                'created_at',
            ),
        ];
    }

    /**
     * @return array{
     *     type: string,
     *     title: string,
     *     message: string
     * }
     */
    public static function fallbackPayload(): array
    {
        return [
            'type' => self::FALLBACK_TYPE,
            'title' => self::FALLBACK_TITLE,
            'message' => self::FALLBACK_MESSAGE,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvePayload(): array
    {
        $rawPayload = $this->resource->getRawOriginal('data');
        $payload = null;

        if (is_array($rawPayload)) {
            $payload = $this->sanitizeArray($rawPayload);
        } elseif (is_string($rawPayload)) {
            $payload = $this->decodePayload($rawPayload);
        }

        if ($payload === null) {
            try {
                $attribute = $this->resource->getAttribute('data');

                if (is_array($attribute)) {
                    $payload = $this->sanitizeArray($attribute);
                }
            } catch (Throwable $exception) {
                $this->logMalformedPayload(
                    'notification payload attribute access failed',
                    [
                        'exception' => $exception::class,
                        'message' => $exception->getMessage(),
                    ],
                );
            }
        }

        return $this->ensureRenderablePayload($payload);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodePayload(string $payload): ?array
    {
        $decoded = json_decode($payload, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            if (is_array($decoded)) {
                return $this->sanitizeArray($decoded);
            }

            $this->logMalformedPayload('notification payload is not an array', [
                'decoded_type' => gettype($decoded),
            ]);

            return null;
        }

        $normalizedPayload = $this->normalizeUtf8($payload);

        if ($normalizedPayload !== null && $normalizedPayload !== $payload) {
            $decoded = json_decode($normalizedPayload, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $this->logMalformedPayload(
                    'notification payload required UTF-8 normalization',
                    [
                        'json_error' => 'utf8_normalized',
                    ],
                );

                return $this->sanitizeArray($decoded);
            }
        }

        $this->logMalformedPayload('notification payload could not be decoded', [
            'json_error' => json_last_error_msg(),
            'raw_length' => strlen($payload),
        ]);

        return null;
    }

    /**
     * @param  array<mixed>  $value
     * @return array<string, mixed>
     */
    private function sanitizeArray(array $value): array
    {
        $sanitized = [];

        foreach ($value as $key => $item) {
            $resolvedKey = $this->resolveKey($key);

            if ($resolvedKey === null) {
                continue;
            }

            $sanitized[$resolvedKey] = $this->sanitizeValue($item);
        }

        return $sanitized;
    }

    private function sanitizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return $this->sanitizeArray($value);
        }

        if (is_string($value)) {
            return $this->normalizeUtf8($value) ?? '';
        }

        if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if ($value instanceof JsonSerializable) {
            return $this->sanitizeValue($value->jsonSerialize());
        }

        if ($value instanceof Stringable) {
            return $this->normalizeUtf8((string) $value) ?? '';
        }

        return null;
    }

    private function resolveKey(mixed $key): ?string
    {
        if (! is_string($key) && ! is_int($key)) {
            return null;
        }

        $resolvedKey = $this->normalizeUtf8((string) $key);

        if ($resolvedKey === null) {
            return null;
        }

        $resolvedKey = trim($resolvedKey);

        return $resolvedKey !== '' ? $resolvedKey : null;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>
     */
    private function ensureRenderablePayload(?array $payload): array
    {
        $payload = $payload ?? [];

        $payload['type'] = $this->resolveRenderableString(
            $payload['type'] ?? null,
            self::FALLBACK_TYPE,
        );
        $payload['title'] = $this->resolveRenderableString(
            $payload['title'] ?? null,
            self::FALLBACK_TITLE,
        );
        $payload['message'] = $this->resolveRenderableString(
            $payload['message'] ?? null,
            self::FALLBACK_MESSAGE,
        );

        return $payload;
    }

    private function resolveRenderableString(
        mixed $value,
        string $fallback,
    ): string {
        if (! is_string($value)) {
            return $fallback;
        }

        $resolvedValue = $this->normalizeUtf8($value);

        if ($resolvedValue === null) {
            return $fallback;
        }

        $resolvedValue = trim($resolvedValue);

        return $resolvedValue !== '' ? $resolvedValue : $fallback;
    }

    private function formatDateValue(mixed $value, string $attribute): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value) && trim($value) !== '') {
            $resolvedValue = $this->normalizeUtf8($value);

            if ($resolvedValue === null) {
                $this->logMalformedPayload('notification date contains invalid UTF-8', [
                    'attribute' => $attribute,
                ]);

                return null;
            }

            try {
                return CarbonImmutable::parse($resolvedValue)->format('Y-m-d H:i:s');
            } catch (Throwable $exception) {
                $this->logMalformedPayload('notification date could not be parsed', [
                    'attribute' => $attribute,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);

                return null;
            }
        }

        return null;
    }

    private function normalizeUtf8(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value === '') {
            return '';
        }

        if ($this->isValidUtf8($value)) {
            return $value;
        }

        if (function_exists('mb_convert_encoding')) {
            $converted = mb_convert_encoding(
                $value,
                'UTF-8',
                'UTF-8,ISO-8859-1,Windows-1252',
            );

            if (is_string($converted) && $this->isValidUtf8($converted)) {
                return $converted;
            }
        }

        $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        if (is_string($converted) && $converted !== '' && $this->isValidUtf8($converted)) {
            return $converted;
        }

        return null;
    }

    private function isValidUtf8(string $value): bool
    {
        if (function_exists('mb_check_encoding')) {
            return mb_check_encoding($value, 'UTF-8');
        }

        return preg_match('//u', $value) === 1;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logMalformedPayload(string $reason, array $context = []): void
    {
        $notification = $this->resource;

        if (! $notification instanceof DatabaseNotification) {
            return;
        }

        Log::warning($reason, array_merge([
            'notification_id' => (string) $notification->getKey(),
            'notification_type' => $notification->type,
            'notifiable_type' => $notification->notifiable_type,
            'notifiable_id' => $notification->notifiable_id,
        ], $context));
    }
}

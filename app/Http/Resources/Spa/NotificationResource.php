<?php

namespace App\Http\Resources\Spa;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Stringable;
use Throwable;
use UnexpectedValueException;

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
            'read_at' => $this->formatDateValue($this->resource->getRawOriginal('read_at')),
            'created_at' => $this->formatDateValue($this->resource->getRawOriginal('created_at')),
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
        $payload = $this->payloadFromRawValue($this->resource->getRawOriginal('data'));

        if ($payload !== null) {
            return $this->ensureRenderablePayload($payload);
        }

        try {
            $payload = $this->payloadFromAttributeValue($this->resource->getAttribute('data'));
        } catch (Throwable $exception) {
            throw new UnexpectedValueException(
                'Notification payload attribute access failed.',
                previous: $exception,
            );
        }

        if ($payload === null) {
            throw new UnexpectedValueException(
                'Notification payload could not be normalized to an array.',
            );
        }

        return $this->ensureRenderablePayload($payload);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function payloadFromRawValue(mixed $payload): ?array
    {
        if (is_array($payload)) {
            return $this->sanitizeArray($payload);
        }

        if (is_string($payload)) {
            return $this->decodePayload($payload);
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function payloadFromAttributeValue(mixed $payload): ?array
    {
        if (! is_array($payload)) {
            return null;
        }

        return $this->sanitizeArray($payload);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodePayload(string $payload): ?array
    {
        $decoded = json_decode($payload, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);

        if (json_last_error() === JSON_ERROR_NONE) {
            if (is_array($decoded)) {
                return $this->sanitizeArray($decoded);
            }

            return null;
        }

        $normalizedPayload = $this->normalizeUtf8($payload);

        if ($normalizedPayload !== null && $normalizedPayload !== $payload) {
            $decoded = json_decode(
                $normalizedPayload,
                true,
                512,
                JSON_INVALID_UTF8_SUBSTITUTE,
            );

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $this->sanitizeArray($decoded);
            }
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $value
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
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function ensureRenderablePayload(array $payload): array
    {
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
        if ($value instanceof Stringable) {
            $value = (string) $value;
        } elseif (is_bool($value) || is_int($value) || is_float($value)) {
            $value = (string) $value;
        } elseif (! is_string($value)) {
            return $fallback;
        }

        $resolvedValue = $this->normalizeUtf8($value);

        if ($resolvedValue === null) {
            return $fallback;
        }

        $resolvedValue = trim($resolvedValue);

        return $resolvedValue !== '' ? $resolvedValue : $fallback;
    }

    private function formatDateValue(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value) && trim($value) !== '') {
            $resolvedValue = $this->normalizeUtf8($value);

            if ($resolvedValue === null) {
                return null;
            }

            try {
                return CarbonImmutable::parse($resolvedValue)->format('Y-m-d H:i:s');
            } catch (Throwable) {
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
}

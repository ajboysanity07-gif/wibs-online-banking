<?php

namespace App\Support;

class DisplayText
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return $trimmed;
        }

        if (! preg_match('/[A-Za-z]/', $trimmed)) {
            return $trimmed;
        }

        $upper = strtoupper($trimmed);

        if ($upper !== $trimmed) {
            return $trimmed;
        }

        $lowered = strtolower($trimmed);
        $normalized = preg_replace_callback(
            '/\\b([a-z])/',
            static fn (array $matches): string => strtoupper($matches[1]),
            $lowered,
        );

        return $normalized ?? $trimmed;
    }

    /**
     * Applies normalize() to the given keys of an associative array,
     * leaving non-string/missing values untouched. Used to convert
     * free-text ALL CAPS input (names, employer names, addresses, etc.)
     * to title case before it is persisted.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    public static function normalizeFields(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (! array_key_exists($field, $data) || ! is_string($data[$field])) {
                continue;
            }

            $data[$field] = self::normalize($data[$field]);
        }

        return $data;
    }
}

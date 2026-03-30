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
}

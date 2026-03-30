<?php

namespace App\Support;

class LocationComposer
{
    public static function compose(
        ?string $address1,
        ?string $address2,
        ?string $address3,
    ): string {
        $parts = [
            $address1,
            $address2,
            $address3,
        ];

        $parts = array_map(
            static fn (?string $value): string => trim((string) $value),
            $parts,
        );
        $parts = array_values(array_filter($parts, static fn (string $value): bool => $value !== ''));

        return implode(', ', $parts);
    }

    public static function composeBirthplace(?string $city, ?string $province): string
    {
        return self::compose($city, $province, null);
    }

    /**
     * @return array{address1: string|null, address2: string|null, address3: string|null}
     */
    public static function parseLegacyAddress(?string $value): array
    {
        $parts = self::splitParts($value);
        $count = count($parts);

        if ($count === 0) {
            return [
                'address1' => null,
                'address2' => null,
                'address3' => null,
            ];
        }

        if ($count === 1) {
            return [
                'address1' => $parts[0],
                'address2' => null,
                'address3' => null,
            ];
        }

        if ($count === 2) {
            return [
                'address1' => $parts[0],
                'address2' => $parts[1],
                'address3' => null,
            ];
        }

        $city = $parts[$count - 2];
        $province = $parts[$count - 1];
        $street = implode(', ', array_slice($parts, 0, $count - 2));

        return [
            'address1' => $street !== '' ? $street : null,
            'address2' => $city,
            'address3' => $province,
        ];
    }

    /**
     * @return array{city: string|null, province: string|null}
     */
    public static function parseLegacyBirthplace(?string $value): array
    {
        $parts = self::splitParts($value);
        $count = count($parts);

        if ($count === 0) {
            return [
                'city' => null,
                'province' => null,
            ];
        }

        if ($count === 1) {
            return [
                'city' => $parts[0],
                'province' => null,
            ];
        }

        $province = $parts[$count - 1];
        $city = implode(', ', array_slice($parts, 0, $count - 1));

        return [
            'city' => $city !== '' ? $city : null,
            'province' => $province,
        ];
    }

    /**
     * @return list<string>
     */
    private static function splitParts(?string $value): array
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return [];
        }

        $parts = array_map(
            static fn (string $part): string => trim($part),
            explode(',', $trimmed),
        );

        $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));

        return $parts;
    }
}

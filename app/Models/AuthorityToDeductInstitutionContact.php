<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuthorityToDeductInstitutionContact extends Model
{
    /** @use HasFactory<\Database\Factories\AuthorityToDeductInstitutionContactFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'institution_name',
        'institution_name_normalized',
        'officer_1_name',
        'officer_1_title',
        'officer_2_name',
        'officer_2_title',
    ];

    public static function normalizeInstitutionName(string $institutionName): string
    {
        return mb_strtolower(trim($institutionName));
    }

    public static function findForInstitution(string $institutionName): ?self
    {
        $normalized = self::normalizeInstitutionName($institutionName);

        if ($normalized === '') {
            return null;
        }

        return self::query()
            ->where('institution_name_normalized', $normalized)
            ->first();
    }

    /**
     * Upsert the saved contact for an institution from staff-entered officer
     * fields. Only called when at least one officer field was actually
     * filled in -- this is how the list grows organically as staff process
     * requests, with no separate admin UI required.
     *
     * @param  array{officer_1_name?: ?string, officer_1_title?: ?string, officer_2_name?: ?string, officer_2_title?: ?string}  $officers
     */
    public static function rememberForInstitution(string $institutionName, array $officers): void
    {
        $normalized = self::normalizeInstitutionName($institutionName);

        if ($normalized === '') {
            return;
        }

        $hasAnyOfficerValue = collect($officers)
            ->filter(static fn (mixed $value): bool => is_string($value) && trim($value) !== '')
            ->isNotEmpty();

        if (! $hasAnyOfficerValue) {
            return;
        }

        self::query()->updateOrCreate(
            ['institution_name_normalized' => $normalized],
            [
                'institution_name' => trim($institutionName),
                'officer_1_name' => $officers['officer_1_name'] ?? null,
                'officer_1_title' => $officers['officer_1_title'] ?? null,
                'officer_2_name' => $officers['officer_2_name'] ?? null,
                'officer_2_title' => $officers['officer_2_title'] ?? null,
            ],
        );
    }
}

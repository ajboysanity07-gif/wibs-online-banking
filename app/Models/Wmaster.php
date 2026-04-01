<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Wmaster extends Model
{
    use HasFactory;

    protected $table = 'wmaster';

    protected $primaryKey = 'acctno';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'acctno',
        'lname',
        'fname',
        'mname',
        'bname',
        'birthplace',
        'phone',
        'email_address',
        'address',
        'address2',
        'address3',
        'address4',
        'birthday',
        'datemem',
        'beneficiary1',
        'beneficiary2',
        'beneficiary3',
        'ben1_bday',
        'ben2_bday',
        'ben3_bday',
        'civilstat',
        'sex',
        'occupation',
        'memtype',
        'district',
        'zoning',
        'dateissued',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'UserPassword',
        'Userrights',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birthday' => 'date',
            'datemem' => 'date',
            'ben1_bday' => 'date',
            'ben2_bday' => 'date',
            'ben3_bday' => 'date',
            'dateissued' => 'date',
        ];
    }

    public function normalizedLastName(): string
    {
        return $this->normalizeValue($this->lname);
    }

    public function normalizedFirstName(): string
    {
        return $this->normalizeValue($this->fname);
    }

    public function normalizedMiddleInitial(): string
    {
        $normalized = $this->normalizedMiddleName();

        return $normalized === '' ? '' : substr($normalized, 0, 1);
    }

    public function normalizedMiddleName(): string
    {
        return $this->normalizeValue($this->mname);
    }

    public function normalizedBirthplace(): string
    {
        return $this->normalizeValue($this->birthplace);
    }

    public function normalizedBname(): string
    {
        return $this->normalizeValue($this->bname);
    }

    public function hasStructuredName(): bool
    {
        return $this->hasValue($this->fname) && $this->hasValue($this->lname);
    }

    public function hasStructuredNameParts(): bool
    {
        return $this->hasValue($this->fname)
            || $this->hasValue($this->mname)
            || $this->hasValue($this->lname);
    }

    public function hasStructuredAddressParts(): bool
    {
        return $this->hasValue($this->address2)
            || $this->hasValue($this->address3)
            || $this->hasValue($this->address4);
    }

    public function displayAddress(): string
    {
        $structuredAddress = $this->structuredAddress();

        if ($structuredAddress !== '') {
            return $structuredAddress;
        }

        return trim((string) $this->address);
    }

    public function structuredAddress(): string
    {
        $parts = [
            $this->address2,
            $this->address3,
            $this->address4,
        ];

        $parts = array_map(
            static fn (mixed $value): string => trim((string) $value),
            $parts,
        );

        $parts = array_values(array_filter($parts, static fn (string $value): bool => $value !== ''));

        return implode(', ', $parts);
    }

    public function displayName(): string
    {
        $structuredName = $this->structuredName();

        if ($structuredName !== '') {
            return $structuredName;
        }

        return trim((string) $this->bname);
    }

    public function structuredName(): string
    {
        $parts = [
            $this->fname,
            $this->mname,
            $this->lname,
        ];

        $parts = array_map(
            static fn (mixed $value): string => trim((string) $value),
            $parts,
        );

        $parts = array_values(array_filter($parts, static fn (string $value): bool => $value !== ''));

        return implode(' ', $parts);
    }

    /**
     * @return array{first_name: string, middle_name: string, last_name: string}
     */
    public function resolvedNameParts(): array
    {
        if ($this->hasStructuredName()) {
            return [
                'first_name' => trim((string) $this->fname),
                'middle_name' => trim((string) $this->mname),
                'last_name' => trim((string) $this->lname),
            ];
        }

        return $this->parseLegacyName($this->bname);
    }

    public function middleInitial(): ?string
    {
        $value = trim((string) $this->mname);

        if ($value === '') {
            return null;
        }

        return substr($value, 0, 1);
    }

    public function hasRequiredProfileFields(): bool
    {
        $hasName = $this->hasStructuredName() || $this->hasValue($this->bname);
        $hasAddress = $this->hasStructuredAddressParts() || $this->hasValue($this->address);

        return $hasName
            && $this->hasValue($this->birthday)
            && $hasAddress
            && $this->hasValue($this->civilstat)
            && $this->hasValue($this->occupation);
    }

    /**
     * @return list<string>
     */
    public function missingRequiredProfileFields(): array
    {
        $missing = [];
        $hasName = $this->hasStructuredName() || $this->hasValue($this->bname);
        $hasAddress = $this->hasStructuredAddressParts() || $this->hasValue($this->address);

        if (! $hasName) {
            $missing[] = 'name';
        }

        if (! $this->hasValue($this->birthday)) {
            $missing[] = 'birthday';
        }

        if (! $hasAddress) {
            $missing[] = 'address';
        }

        if (! $this->hasValue($this->civilstat)) {
            $missing[] = 'civil_status';
        }

        if (! $this->hasValue($this->occupation)) {
            $missing[] = 'occupation';
        }

        return $missing;
    }

    /**
     * @return list<string>
     */
    public function missingRequiredProfileFieldLabels(): array
    {
        $labels = [
            'name' => 'Name',
            'birthday' => 'Birthday',
            'address' => 'Address',
            'civil_status' => 'Civil status',
            'occupation' => 'Occupation',
        ];

        return array_values(array_map(
            static fn (string $field): string => $labels[$field] ?? $field,
            $this->missingRequiredProfileFields(),
        ));
    }

    private function normalizeValue(?string $value): string
    {
        $normalized = Str::upper(trim((string) $value));
        $normalized = preg_replace('/[.,\-]/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    /**
     * @return array{first_name: string, middle_name: string, last_name: string}
     */
    private function parseLegacyName(?string $legacyName): array
    {
        $value = trim((string) $legacyName);

        if ($value === '') {
            return [
                'first_name' => '',
                'middle_name' => '',
                'last_name' => '',
            ];
        }

        if (str_contains($value, ',')) {
            [$lastPart, $rest] = array_pad(explode(',', $value, 2), 2, '');
            $last = trim($lastPart);
            $rest = trim($rest);
        } else {
            $parts = preg_split('/\s+/', $value) ?: [];
            $last = (string) array_pop($parts);
            $rest = trim(implode(' ', $parts));
        }

        $restParts = $rest !== '' ? preg_split('/\s+/', $rest) ?: [] : [];
        $first = $restParts !== [] ? (string) array_shift($restParts) : '';
        $middle = $restParts !== [] ? trim(implode(' ', $restParts)) : '';

        return [
            'first_name' => $first,
            'middle_name' => $middle,
            'last_name' => $last,
        ];
    }

    private function hasValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return true;
    }
}

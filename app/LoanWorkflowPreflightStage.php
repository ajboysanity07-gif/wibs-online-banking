<?php

namespace App;

enum LoanWorkflowPreflightStage: string
{
    case PreMigration = 'pre-migration';
    case PostMigration = 'post-migration';

    public static function parse(?string $value): ?self
    {
        $normalized = trim((string) $value);

        return match ($normalized) {
            '',
            self::PostMigration->value,
            'post',
            'strict' => self::PostMigration,
            self::PreMigration->value,
            'pre' => self::PreMigration,
            'before-migration' => self::PreMigration,
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PreMigration => 'Pre-migration',
            self::PostMigration => 'Post-migration',
        };
    }

    public function isPreMigration(): bool
    {
        return $this === self::PreMigration;
    }
}

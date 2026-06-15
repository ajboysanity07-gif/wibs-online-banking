<?php

namespace App;

enum LoanRequestDocumentReadinessStatus: string
{
    case NotStarted = 'not_started';
    case Incomplete = 'incomplete';
    case AwaitingMemberConfirmation = 'awaiting_member_confirmation';
    case ReadyToGenerate = 'ready_to_generate';
    case GeneratedCurrent = 'generated_current';
    case GeneratedStale = 'generated_stale';
    case GenerationFailed = 'generation_failed';
    case NotApplicable = 'not_applicable';
    case LegacyDataIncomplete = 'legacy_data_incomplete';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not Started',
            self::Incomplete => 'Incomplete',
            self::AwaitingMemberConfirmation => 'Awaiting Member Confirmation',
            self::ReadyToGenerate => 'Ready to Generate',
            self::GeneratedCurrent => 'Generated - Current',
            self::GeneratedStale => 'Generated - Stale',
            self::GenerationFailed => 'Generation Failed',
            self::NotApplicable => 'Not Applicable',
            self::LegacyDataIncomplete => 'Legacy Data Incomplete',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases(),
        );
    }
}

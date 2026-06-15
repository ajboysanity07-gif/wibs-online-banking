<?php

namespace App;

enum LoanRequestWorkflowVersion: string
{
    case LegacyV1 = 'legacy_v1';
    case DocumentWorkflowV2 = 'document_workflow_v2';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $version): string => $version->value,
            self::cases(),
        );
    }
}

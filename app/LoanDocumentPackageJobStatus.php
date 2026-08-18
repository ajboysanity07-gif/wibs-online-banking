<?php

namespace App;

enum LoanDocumentPackageJobStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Processing => 'Preparing',
            self::Completed => 'Ready',
            self::Failed => 'Failed',
        };
    }

    /**
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::Queued, self::Processing];
    }
}

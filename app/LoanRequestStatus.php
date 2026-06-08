<?php

namespace App;

enum LoanRequestStatus: string
{
    case Draft = 'draft';
    case PendingCoMakerSignatures = 'pending_co_maker_signatures';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Declined = 'declined';
    case Cancelled = 'cancelled';

    public function normalized(): self
    {
        return match ($this) {
            self::PendingCoMakerSignatures,
            self::Submitted => self::UnderReview,
            default => $this,
        };
    }

    public static function normalizeValue(self|string|null $status): ?string
    {
        if ($status === null) {
            return null;
        }

        if (is_string($status)) {
            return self::tryFrom($status)?->normalized()->value ?? $status;
        }

        return $status->normalized()->value;
    }

    /**
     * @return list<string>
     */
    public static function memberVisibleValue(self|string|null $status): ?string
    {
        if ($status === null) {
            return null;
        }

        $value = $status instanceof self
            ? $status->value
            : $status;

        if ($value === self::PendingCoMakerSignatures->value) {
            return self::Draft->value;
        }

        return self::normalizeValue($status);
    }

    /**
     * @return list<string>
     */
    public static function pendingDecisionValues(): array
    {
        return [
            self::PendingCoMakerSignatures->value,
            self::Submitted->value,
            self::UnderReview->value,
        ];
    }

    /**
     * @return list<string>
     */
    public static function requestFilterValues(): array
    {
        return [
            self::Draft->value,
            self::Submitted->value,
            self::UnderReview->value,
            self::Approved->value,
            self::Declined->value,
            self::Cancelled->value,
        ];
    }
}

<?php

namespace App;

enum LoanRequestStatus: string
{
    case Draft = 'draft';
    case PendingCoMakerSignatures = 'pending_co_maker_signatures';
    case Submitted = 'submitted';
    case PendingReview = 'pending_review';
    case UnderReview = 'under_review';
    case NeedsRevision = 'needs_revision';
    case RecommendedForApproval = 'recommended_for_approval';
    case Rejected = 'rejected';
    case Approved = 'approved';
    case Declined = 'declined';
    case ConvertedToLoan = 'converted_to_loan';
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
            self::PendingReview->value,
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
            self::PendingReview->value,
            self::UnderReview->value,
            self::NeedsRevision->value,
            self::RecommendedForApproval->value,
            self::Rejected->value,
            self::Approved->value,
            self::Declined->value,
            self::ConvertedToLoan->value,
            self::Cancelled->value,
        ];
    }

    /**
     * @return list<string>
     */
    public static function workflowValues(): array
    {
        return [
            self::PendingReview->value,
            self::UnderReview->value,
            self::NeedsRevision->value,
            self::RecommendedForApproval->value,
            self::Rejected->value,
            self::Approved->value,
            self::Declined->value,
            self::ConvertedToLoan->value,
        ];
    }
}

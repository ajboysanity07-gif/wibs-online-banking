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
    case AwaitingMemberInformation = 'awaiting_member_information';
    case RecommendedForApproval = 'recommended_for_approval';
    case AwaitingMemberAcceptance = 'awaiting_member_acceptance';
    case Rejected = 'rejected';
    case Approved = 'approved';
    case Declined = 'declined';
    case MemberDeclinedTerms = 'member_declined_terms';
    case ConvertedToLoan = 'converted_to_loan';
    case ForWibsEncoding = 'for_wibs_encoding';
    case WibsLoanCreated = 'wibs_loan_created';
    case ReleaseScheduled = 'release_scheduled';
    case Released = 'released';
    case Cancelled = 'cancelled';

    public function normalized(): self
    {
        return match ($this) {
            self::PendingCoMakerSignatures,
            self::Submitted => self::PendingReview,
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
            self::NeedsRevision->value,
            self::AwaitingMemberInformation->value,
            self::AwaitingMemberAcceptance->value,
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
            self::AwaitingMemberInformation->value,
            self::RecommendedForApproval->value,
            self::AwaitingMemberAcceptance->value,
            self::Rejected->value,
            self::Approved->value,
            self::Declined->value,
            self::MemberDeclinedTerms->value,
            self::ConvertedToLoan->value,
            self::ForWibsEncoding->value,
            self::WibsLoanCreated->value,
            self::ReleaseScheduled->value,
            self::Released->value,
            self::Cancelled->value,
        ];
    }

    /**
     * Superadmin-only "undo the last transition" map: current status value =>
     * status value to revert to. One step back only, and only while the
     * request is still pre-conversion -- once it reaches ConvertedToLoan (or
     * later) external WIBS/release records may already exist, so reverting
     * the loan_requests row alone would desync it from those records.
     *
     * @return array<string, string>
     */
    public static function revertMap(): array
    {
        return [
            self::UnderReview->value => self::PendingReview->value,
            self::RecommendedForApproval->value => self::UnderReview->value,
            self::AwaitingMemberAcceptance->value => self::RecommendedForApproval->value,
            self::Approved->value => self::RecommendedForApproval->value,
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
            self::AwaitingMemberInformation->value,
            self::RecommendedForApproval->value,
            self::AwaitingMemberAcceptance->value,
            self::Rejected->value,
            self::Approved->value,
            self::Declined->value,
            self::MemberDeclinedTerms->value,
            self::ConvertedToLoan->value,
            self::ForWibsEncoding->value,
            self::WibsLoanCreated->value,
            self::ReleaseScheduled->value,
            self::Released->value,
        ];
    }
}

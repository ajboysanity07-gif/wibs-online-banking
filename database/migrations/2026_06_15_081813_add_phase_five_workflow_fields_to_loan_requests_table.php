<?php

use App\LoanRequestWorkflowVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('loan_requests')) {
            return;
        }

        $hasWorkflowVersion = Schema::hasColumn('loan_requests', 'workflow_version');
        $hasRecommendedAmount = Schema::hasColumn('loan_requests', 'recommended_amount');
        $hasRecommendedTerm = Schema::hasColumn('loan_requests', 'recommended_term');
        $hasRecommendedInterestRate = Schema::hasColumn('loan_requests', 'recommended_interest_rate');
        $hasRecommendedPaymentFrequency = Schema::hasColumn('loan_requests', 'recommended_payment_frequency');
        $hasRecommendationRemarks = Schema::hasColumn('loan_requests', 'recommendation_remarks');
        $hasReviewRejectionCategory = Schema::hasColumn('loan_requests', 'review_rejection_category');
        $hasDeclineCategory = Schema::hasColumn('loan_requests', 'decline_category');
        $hasMemberActionType = Schema::hasColumn('loan_requests', 'member_action_type');
        $hasMemberActionMessage = Schema::hasColumn('loan_requests', 'member_action_message');
        $hasMemberActionFieldsJson = Schema::hasColumn('loan_requests', 'member_action_fields_json');
        $hasMemberActionRequestedBy = Schema::hasColumn('loan_requests', 'member_action_requested_by');
        $hasMemberActionRequestedAt = Schema::hasColumn('loan_requests', 'member_action_requested_at');
        $hasMemberActionResolvedAt = Schema::hasColumn('loan_requests', 'member_action_resolved_at');
        $hasWorkflowUpgradedBy = Schema::hasColumn('loan_requests', 'workflow_upgraded_by');
        $hasWorkflowUpgradedAt = Schema::hasColumn('loan_requests', 'workflow_upgraded_at');
        $hasWorkflowUpgradeReason = Schema::hasColumn('loan_requests', 'workflow_upgrade_reason');
        $hasReopenedBy = Schema::hasColumn('loan_requests', 'reopened_by');
        $hasReopenedAt = Schema::hasColumn('loan_requests', 'reopened_at');

        Schema::table('loan_requests', function (Blueprint $table) use (
            $hasWorkflowVersion,
            $hasRecommendedAmount,
            $hasRecommendedTerm,
            $hasRecommendedInterestRate,
            $hasRecommendedPaymentFrequency,
            $hasRecommendationRemarks,
            $hasReviewRejectionCategory,
            $hasDeclineCategory,
            $hasMemberActionType,
            $hasMemberActionMessage,
            $hasMemberActionFieldsJson,
            $hasMemberActionRequestedBy,
            $hasMemberActionRequestedAt,
            $hasMemberActionResolvedAt,
            $hasWorkflowUpgradedBy,
            $hasWorkflowUpgradedAt,
            $hasWorkflowUpgradeReason,
            $hasReopenedBy,
            $hasReopenedAt,
        ): void {
            if (! $hasWorkflowVersion) {
                $table->string('workflow_version')
                    ->default(LoanRequestWorkflowVersion::DocumentWorkflowV2->value)
                    ->after('status');
            }

            if (! $hasRecommendedAmount) {
                $table->decimal('recommended_amount', 12, 2)
                    ->nullable()
                    ->after('approved_interest_rate');
            }

            if (! $hasRecommendedTerm) {
                $table->unsignedSmallInteger('recommended_term')
                    ->nullable()
                    ->after('recommended_amount');
            }

            if (! $hasRecommendedInterestRate) {
                $table->decimal('recommended_interest_rate', 12, 4)
                    ->nullable()
                    ->after('recommended_term');
            }

            if (! $hasRecommendedPaymentFrequency) {
                $table->string('recommended_payment_frequency')
                    ->nullable()
                    ->after('recommended_interest_rate');
            }

            if (! $hasRecommendationRemarks) {
                $table->text('recommendation_remarks')
                    ->nullable()
                    ->after('recommended_payment_frequency');
            }

            if (! $hasReviewRejectionCategory) {
                $table->string('review_rejection_category')
                    ->nullable()
                    ->after('rejection_reason');
            }

            if (! $hasDeclineCategory) {
                $table->string('decline_category')
                    ->nullable()
                    ->after('decline_reason');
            }

            if (! $hasMemberActionType) {
                $table->string('member_action_type')
                    ->nullable()
                    ->after('recommendation_remarks');
            }

            if (! $hasMemberActionMessage) {
                $table->text('member_action_message')
                    ->nullable()
                    ->after('member_action_type');
            }

            if (! $hasMemberActionFieldsJson) {
                $table->text('member_action_fields_json')
                    ->nullable()
                    ->after('member_action_message');
            }

            if (! $hasMemberActionRequestedBy) {
                $table->unsignedBigInteger('member_action_requested_by')
                    ->nullable()
                    ->after('member_action_fields_json');
            }

            if (! $hasMemberActionRequestedAt) {
                $table->timestamp('member_action_requested_at')
                    ->nullable()
                    ->after('member_action_requested_by');
            }

            if (! $hasMemberActionResolvedAt) {
                $table->timestamp('member_action_resolved_at')
                    ->nullable()
                    ->after('member_action_requested_at');
            }

            if (! $hasWorkflowUpgradedBy) {
                $table->unsignedBigInteger('workflow_upgraded_by')
                    ->nullable()
                    ->after('member_action_resolved_at');
            }

            if (! $hasWorkflowUpgradedAt) {
                $table->timestamp('workflow_upgraded_at')
                    ->nullable()
                    ->after('workflow_upgraded_by');
            }

            if (! $hasWorkflowUpgradeReason) {
                $table->text('workflow_upgrade_reason')
                    ->nullable()
                    ->after('workflow_upgraded_at');
            }

            if (! $hasReopenedBy) {
                $table->unsignedBigInteger('reopened_by')
                    ->nullable()
                    ->after('workflow_upgrade_reason');
            }

            if (! $hasReopenedAt) {
                $table->timestamp('reopened_at')
                    ->nullable()
                    ->after('reopened_by');
            }
        });

        DB::table('loan_requests')->update([
            'workflow_version' => LoanRequestWorkflowVersion::LegacyV1->value,
        ]);

        DB::table('loan_requests')
            ->where('status', 'pending_co_maker_signatures')
            ->update([
                'status' => 'pending_review',
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('loan_requests')) {
            return;
        }

        $columns = array_values(array_filter([
            Schema::hasColumn('loan_requests', 'reopened_at') ? 'reopened_at' : null,
            Schema::hasColumn('loan_requests', 'reopened_by') ? 'reopened_by' : null,
            Schema::hasColumn('loan_requests', 'workflow_upgrade_reason') ? 'workflow_upgrade_reason' : null,
            Schema::hasColumn('loan_requests', 'workflow_upgraded_at') ? 'workflow_upgraded_at' : null,
            Schema::hasColumn('loan_requests', 'workflow_upgraded_by') ? 'workflow_upgraded_by' : null,
            Schema::hasColumn('loan_requests', 'member_action_resolved_at') ? 'member_action_resolved_at' : null,
            Schema::hasColumn('loan_requests', 'member_action_requested_at') ? 'member_action_requested_at' : null,
            Schema::hasColumn('loan_requests', 'member_action_requested_by') ? 'member_action_requested_by' : null,
            Schema::hasColumn('loan_requests', 'member_action_fields_json') ? 'member_action_fields_json' : null,
            Schema::hasColumn('loan_requests', 'member_action_message') ? 'member_action_message' : null,
            Schema::hasColumn('loan_requests', 'member_action_type') ? 'member_action_type' : null,
            Schema::hasColumn('loan_requests', 'recommendation_remarks') ? 'recommendation_remarks' : null,
            Schema::hasColumn('loan_requests', 'recommended_payment_frequency') ? 'recommended_payment_frequency' : null,
            Schema::hasColumn('loan_requests', 'recommended_interest_rate') ? 'recommended_interest_rate' : null,
            Schema::hasColumn('loan_requests', 'recommended_term') ? 'recommended_term' : null,
            Schema::hasColumn('loan_requests', 'recommended_amount') ? 'recommended_amount' : null,
            Schema::hasColumn('loan_requests', 'decline_category') ? 'decline_category' : null,
            Schema::hasColumn('loan_requests', 'review_rejection_category') ? 'review_rejection_category' : null,
            Schema::hasColumn('loan_requests', 'workflow_version') ? 'workflow_version' : null,
        ]));

        if ($columns === []) {
            return;
        }

        Schema::table('loan_requests', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }
};

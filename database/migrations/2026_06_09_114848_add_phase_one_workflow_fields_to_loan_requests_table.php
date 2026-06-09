<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_requests')) {
            return;
        }

        $hasAssignedOfficer = Schema::hasColumn('loan_requests', 'assigned_officer_id');
        $hasReviewedBy = Schema::hasColumn('loan_requests', 'reviewed_by');
        $hasReviewedAt = Schema::hasColumn('loan_requests', 'reviewed_at');
        $hasReviewRemarks = Schema::hasColumn('loan_requests', 'review_remarks');
        $hasReviewDecision = Schema::hasColumn('loan_requests', 'review_decision');
        $hasRejectedBy = Schema::hasColumn('loan_requests', 'rejected_by');
        $hasRejectedAt = Schema::hasColumn('loan_requests', 'rejected_at');
        $hasRejectionReason = Schema::hasColumn('loan_requests', 'rejection_reason');
        $hasApprovedBy = Schema::hasColumn('loan_requests', 'approved_by');
        $hasApprovedAt = Schema::hasColumn('loan_requests', 'approved_at');
        $hasApprovalRemarks = Schema::hasColumn('loan_requests', 'approval_remarks');
        $hasApprovedAmount = Schema::hasColumn('loan_requests', 'approved_amount');
        $hasApprovedTerm = Schema::hasColumn('loan_requests', 'approved_term');
        $hasApprovedInterestRate = Schema::hasColumn('loan_requests', 'approved_interest_rate');
        $hasDeclinedBy = Schema::hasColumn('loan_requests', 'declined_by');
        $hasDeclinedAt = Schema::hasColumn('loan_requests', 'declined_at');
        $hasDeclineReason = Schema::hasColumn('loan_requests', 'decline_reason');

        Schema::table('loan_requests', function (Blueprint $table) use (
            $hasAssignedOfficer,
            $hasReviewedBy,
            $hasReviewedAt,
            $hasReviewRemarks,
            $hasReviewDecision,
            $hasRejectedBy,
            $hasRejectedAt,
            $hasRejectionReason,
            $hasApprovedBy,
            $hasApprovedAt,
            $hasApprovalRemarks,
            $hasApprovedAmount,
            $hasApprovedTerm,
            $hasApprovedInterestRate,
            $hasDeclinedBy,
            $hasDeclinedAt,
            $hasDeclineReason,
        ) {
            if (! $hasAssignedOfficer) {
                $table->unsignedBigInteger('assigned_officer_id')
                    ->nullable()
                    ->after('submitted_at');

                $assignedOfficerForeignKey = $table->foreign('assigned_officer_id')
                    ->references('user_id')
                    ->on('appusers');

                if (Schema::getConnection()->getDriverName() === 'sqlsrv') {
                    $assignedOfficerForeignKey->onDelete('no action');
                } else {
                    $assignedOfficerForeignKey
                        ->cascadeOnUpdate()
                        ->nullOnDelete();
                }
            }

            if (! $hasReviewedBy) {
                $table->unsignedBigInteger('reviewed_by')
                    ->nullable()
                    ->after('assigned_officer_id');

                $reviewedByForeignKey = $table->foreign('reviewed_by')
                    ->references('user_id')
                    ->on('appusers');

                if (Schema::getConnection()->getDriverName() === 'sqlsrv') {
                    $reviewedByForeignKey->onDelete('no action');
                } else {
                    $reviewedByForeignKey
                        ->cascadeOnUpdate()
                        ->nullOnDelete();
                }
            }

            if (! $hasReviewedAt) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }

            if (! $hasReviewDecision) {
                $table->string('review_decision')->nullable()->after('reviewed_at');
            }

            if (! $hasReviewRemarks) {
                $table->text('review_remarks')->nullable()->after('review_decision');
            }

            if (! $hasRejectedBy) {
                $table->unsignedBigInteger('rejected_by')
                    ->nullable()
                    ->after('review_remarks');

                $rejectedByForeignKey = $table->foreign('rejected_by')
                    ->references('user_id')
                    ->on('appusers');

                if (Schema::getConnection()->getDriverName() === 'sqlsrv') {
                    $rejectedByForeignKey->onDelete('no action');
                } else {
                    $rejectedByForeignKey
                        ->cascadeOnUpdate()
                        ->nullOnDelete();
                }
            }

            if (! $hasRejectedAt) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            }

            if (! $hasRejectionReason) {
                $table->text('rejection_reason')->nullable()->after('rejected_at');
            }

            if (! $hasApprovedBy) {
                $table->unsignedBigInteger('approved_by')
                    ->nullable()
                    ->after('rejection_reason');

                $approvedByForeignKey = $table->foreign('approved_by')
                    ->references('user_id')
                    ->on('appusers');

                if (Schema::getConnection()->getDriverName() === 'sqlsrv') {
                    $approvedByForeignKey->onDelete('no action');
                } else {
                    $approvedByForeignKey
                        ->cascadeOnUpdate()
                        ->nullOnDelete();
                }
            }

            if (! $hasApprovedAt) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }

            if (! $hasApprovalRemarks) {
                $table->text('approval_remarks')->nullable()->after('approved_at');
            }

            if (! $hasApprovedAmount) {
                $table->decimal('approved_amount', 12, 2)->nullable()->after('approval_remarks');
            }

            if (! $hasApprovedTerm) {
                $table->unsignedSmallInteger('approved_term')->nullable()->after('approved_amount');
            }

            if (! $hasApprovedInterestRate) {
                $table->decimal('approved_interest_rate', 8, 4)
                    ->nullable()
                    ->after('approved_term');
            }

            if (! $hasDeclinedBy) {
                $table->unsignedBigInteger('declined_by')
                    ->nullable()
                    ->after('approved_interest_rate');

                $declinedByForeignKey = $table->foreign('declined_by')
                    ->references('user_id')
                    ->on('appusers');

                if (Schema::getConnection()->getDriverName() === 'sqlsrv') {
                    $declinedByForeignKey->onDelete('no action');
                } else {
                    $declinedByForeignKey
                        ->cascadeOnUpdate()
                        ->nullOnDelete();
                }
            }

            if (! $hasDeclinedAt) {
                $table->timestamp('declined_at')->nullable()->after('declined_by');
            }

            if (! $hasDeclineReason) {
                $table->text('decline_reason')->nullable()->after('declined_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('loan_requests')) {
            return;
        }

        foreach ([
            'assigned_officer_id',
            'rejected_by',
            'approved_by',
            'declined_by',
        ] as $foreignColumn) {
            if (! Schema::hasColumn('loan_requests', $foreignColumn)) {
                continue;
            }

            try {
                Schema::table('loan_requests', function (Blueprint $table) use ($foreignColumn) {
                    $table->dropForeign([$foreignColumn]);
                });
            } catch (\Throwable) {
            }
        }

        $columns = array_values(array_filter([
            Schema::hasColumn('loan_requests', 'decline_reason') ? 'decline_reason' : null,
            Schema::hasColumn('loan_requests', 'declined_at') ? 'declined_at' : null,
            Schema::hasColumn('loan_requests', 'declined_by') ? 'declined_by' : null,
            Schema::hasColumn('loan_requests', 'approved_interest_rate') ? 'approved_interest_rate' : null,
            Schema::hasColumn('loan_requests', 'approval_remarks') ? 'approval_remarks' : null,
            Schema::hasColumn('loan_requests', 'approved_at') ? 'approved_at' : null,
            Schema::hasColumn('loan_requests', 'approved_by') ? 'approved_by' : null,
            Schema::hasColumn('loan_requests', 'rejection_reason') ? 'rejection_reason' : null,
            Schema::hasColumn('loan_requests', 'rejected_at') ? 'rejected_at' : null,
            Schema::hasColumn('loan_requests', 'rejected_by') ? 'rejected_by' : null,
            Schema::hasColumn('loan_requests', 'review_remarks') ? 'review_remarks' : null,
            Schema::hasColumn('loan_requests', 'review_decision') ? 'review_decision' : null,
            Schema::hasColumn('loan_requests', 'assigned_officer_id') ? 'assigned_officer_id' : null,
        ]));

        if ($columns === []) {
            return;
        }

        Schema::table('loan_requests', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};

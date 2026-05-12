<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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

        $hasCancelledBy = Schema::hasColumn('loan_requests', 'cancelled_by');
        $hasCancelledAt = Schema::hasColumn('loan_requests', 'cancelled_at');
        $hasCancellationReason = Schema::hasColumn('loan_requests', 'cancellation_reason');

        Schema::table('loan_requests', function (Blueprint $table) use ($hasCancelledBy, $hasCancelledAt, $hasCancellationReason) {
            if (! $hasCancelledBy) {
                $table->unsignedBigInteger('cancelled_by')->nullable()->after('decision_notes');

                $cancelledByForeignKey = $table->foreign('cancelled_by')
                    ->references('user_id')
                    ->on('appusers');

                if (Schema::getConnection()->getDriverName() === 'sqlsrv') {
                    $cancelledByForeignKey->onDelete('no action');
                } else {
                    $cancelledByForeignKey
                        ->cascadeOnUpdate()
                        ->nullOnDelete();
                }
            }

            if (! $hasCancelledAt) {
                $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
            }

            if (! $hasCancellationReason) {
                $table->text('cancellation_reason')->nullable()->after('cancelled_at');
            }
        });

        if (! Schema::hasTable('loan_request_changes')) {
            return;
        }

        $hasAction = Schema::hasColumn('loan_request_changes', 'action');
        $hasChangedFieldsJson = Schema::hasColumn('loan_request_changes', 'changed_fields_json');

        Schema::table('loan_request_changes', function (Blueprint $table) use ($hasAction, $hasChangedFieldsJson) {
            if (! $hasAction) {
                $table->string('action')->nullable()->after('changed_by');
            }

            if (! $hasChangedFieldsJson) {
                $table->json('changed_fields_json')->nullable()->after('after_json');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('loan_request_changes')) {
            $changeColumns = array_values(array_filter([
                Schema::hasColumn('loan_request_changes', 'changed_fields_json') ? 'changed_fields_json' : null,
                Schema::hasColumn('loan_request_changes', 'action') ? 'action' : null,
            ]));

            if ($changeColumns !== []) {
                Schema::table('loan_request_changes', function (Blueprint $table) use ($changeColumns) {
                    $table->dropColumn($changeColumns);
                });
            }
        }

        if (! Schema::hasTable('loan_requests')) {
            return;
        }

        if (Schema::hasColumn('loan_requests', 'cancelled_by')) {
            try {
                Schema::table('loan_requests', function (Blueprint $table) {
                    $table->dropForeign(['cancelled_by']);
                });
            } catch (\Throwable) {
            }
        }

        $columns = array_values(array_filter([
            Schema::hasColumn('loan_requests', 'cancellation_reason') ? 'cancellation_reason' : null,
            Schema::hasColumn('loan_requests', 'cancelled_at') ? 'cancelled_at' : null,
            Schema::hasColumn('loan_requests', 'cancelled_by') ? 'cancelled_by' : null,
        ]));

        if ($columns !== []) {
            Schema::table('loan_requests', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('approval_signature_id')
                ->nullable()
                ->after('reviewed_at')
                ->index();
            $table->string('approval_ip_address', 45)
                ->nullable()
                ->after('approval_signature_id');
            $table->text('approval_user_agent')
                ->nullable()
                ->after('approval_ip_address');

            $approvalSignatureForeignKey = $table->foreign(
                'approval_signature_id',
            )->references('id')->on('admin_signatures');

            if (Schema::getConnection()->getDriverName() === 'sqlsrv') {
                $approvalSignatureForeignKey->onDelete('no action');
            } else {
                $approvalSignatureForeignKey
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_requests', function (Blueprint $table) {
            $table->dropForeign(['approval_signature_id']);
            $table->dropColumn([
                'approval_signature_id',
                'approval_ip_address',
                'approval_user_agent',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drops schema left over from the removed digital co-maker/loan-manager
     * signature-capture flow (superseded by physical wet-ink signing).
     * Nothing in the application reads these tables/columns anymore.
     */
    public function up(): void
    {
        Schema::table('loan_requests', function (Blueprint $table) {
            $table->dropForeign(['approval_signature_id']);
            $table->dropIndex(['approval_signature_id']);
            $table->dropColumn('approval_signature_id');
        });

        Schema::dropIfExists('loan_request_signature_links');

        Schema::table('loan_request_people', function (Blueprint $table) {
            $table->dropColumn('signature_path');
        });

        Schema::dropIfExists('admin_signatures');
    }

    /**
     * Rollback note: recreates the dropped tables/column/FK with their
     * original structure. Historical rows in admin_signatures and
     * loan_request_signature_links are not recoverable once up() runs
     * against a real database — this only restores the schema shape.
     */
    public function down(): void
    {
        Schema::create('admin_signatures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('signature_path');
            $table->boolean('is_active')->default(true)->index();
            $table->string('created_ip', 45)->nullable();
            $table->text('created_user_agent')->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('user_id')
                ->on('appusers')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });

        Schema::table('loan_request_people', function (Blueprint $table) {
            $table->string('signature_path')->nullable()->after('role');
        });

        Schema::create('loan_request_signature_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_request_id')
                ->constrained('loan_requests')
                ->cascadeOnDelete();
            $table->foreignId('loan_request_person_id')
                ->constrained('loan_request_people')
                ->noActionOnDelete();
            $table->string('role', 32)->index();
            $table->string('token_hash', 64)->unique();
            $table->dateTime('expires_at')->index();
            $table->dateTime('signed_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::table('loan_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('approval_signature_id')
                ->nullable()
                ->after('reviewed_at')
                ->index();

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
};

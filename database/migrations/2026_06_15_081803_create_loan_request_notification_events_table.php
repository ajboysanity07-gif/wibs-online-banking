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
        if (Schema::hasTable('loan_request_notification_events')) {
            return;
        }

        Schema::create('loan_request_notification_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loan_request_id');
            $table->string('event_type');
            $table->string('channel');
            $table->unsignedBigInteger('recipient_user_id')->nullable();
            $table->string('recipient')->nullable();
            $table->string('result')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->text('metadata_json')->nullable();
            $table->timestamps();

            $table->unique(
                ['loan_request_id', 'event_type', 'channel', 'recipient'],
                'loan_request_notification_events_dedupe',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_request_notification_events');
    }
};

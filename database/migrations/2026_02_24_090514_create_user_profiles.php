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
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            // Profile pic
            $table->string('profile_pic_path')->nullable();
            // PRC Front and Back
            $table->string('prc_front_path')->nullable();
            $table->string('prc_back_path')->nullable();
            // Payslip photo
            $table->string('payslip_path')->nullable();
            $table->string('role', 20)->default('client');
            $table->string('status', 20)->default('pending');
            // Review history
            $table->unsignedBigInteger('reviewed_by')->nullable()->index();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('user_id')
                ->on('appusers')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreign('reviewed_by')
                ->references('user_id')
                ->on('appusers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};

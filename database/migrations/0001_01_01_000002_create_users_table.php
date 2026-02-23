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
        Schema::create('appusers', function (Blueprint $table) {
            $table->string('acctno', 6)->primary();
            $table->string('email')->unique()->index();
            $table->string('username')->unique()->index();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            //Profile pic
            $table->string('profile_pic_path')->nullable();
            //PRC Front and Back
            $table->string('prc_front_path')->nullable();
            $table->string('prc_back_path')->nullable();
            //Payslip photo
            $table->string('payslip_path')->nullable();
            //Role and status
            // $table->string('role', 20)->default('client');
            $table->string('status', 20)->default('pending');
            //Review history
            $table->string('reviewed_by',6)->nullable()->index();
            $table->dateTime('reviewed_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            //FK to wmaster
            $table->foreign('acctno')
                ->references('acctno')
                ->on('wmaster')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            //FK to wmaster
            $table->foreign('reviewed_by')
                ->references('admin_id')
                ->on('adminusers')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('user_id', 64)->nullable()->index();
            $table->string('user_type', 32)->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('appusers');
        Schema::dropIfExists('password_reset_tokens');
      
    }
};

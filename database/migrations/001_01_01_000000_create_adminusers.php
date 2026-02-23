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
        Schema::create('adminusers', function (Blueprint $table) {
            $table->string('admin_id', 6)->primary();
            $table->string('email')->unique()->index();
            $table->string('username')->unique()->index();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            //Profile pic
            $table->string('profile_pic_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adminusers');
    }
};

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
        Schema::create('admin_profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->string('fullname');
            // Profile pic
            $table->string('profile_pic_path')->nullable();
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
        Schema::dropIfExists('admin_profiles');
    }
};

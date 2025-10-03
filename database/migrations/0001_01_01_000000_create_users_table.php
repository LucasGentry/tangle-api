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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();

            $table->string('username')->nullable();
            $table->string('tagline')->nullable();
            $table->string('location')->nullable();
            $table->text('bio')->nullable();
            $table->string('profile_photo')->nullable();
            $table->json('social_links')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamps();

            $table->json('portfolio_images')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->json('social_media')->nullable();
        });

        // Add column positions after table creation
        Schema::table('users', function (Blueprint $table) {
            $table->json('portfolio_images')->nullable()->after('profile_photo')->change();
            $table->string('first_name')->nullable()->after('name')->change();
            $table->string('last_name')->nullable()->after('first_name')->change();
            $table->json('social_media')->nullable()->after('social_links')->change();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
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
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};

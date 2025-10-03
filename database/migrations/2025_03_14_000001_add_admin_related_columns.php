<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Add admin-related columns to users table
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_suspended')->default(false);
            $table->timestamp('suspended_at')->nullable();
            $table->string('suspension_reason')->nullable();
        });

        // Add moderation columns to messages table
        Schema::table('messages', function (Blueprint $table) {
            $table->boolean('is_reported')->default(false);
            $table->string('report_reason')->nullable();
            $table->string('report_status')->default('pending');
            $table->boolean('is_hidden')->default(false);
            $table->text('admin_notes')->nullable();
            $table->timestamp('admin_reviewed_at')->nullable();
        });

        // Add moderation columns to collaboration_requests table
        Schema::table('collaboration_requests', function (Blueprint $table) {
            $table->boolean('is_reported')->default(false);
            $table->string('report_reason')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->text('admin_notes')->nullable();
            $table->timestamp('admin_reviewed_at')->nullable();
        });

        // Add dispute columns to stripe_transactions table
        Schema::table('stripe_transactions', function (Blueprint $table) {
            $table->boolean('has_dispute')->default(false);
            $table->string('dispute_status')->nullable();
            $table->text('dispute_reason')->nullable();
            $table->text('dispute_resolution')->nullable();
            $table->timestamp('dispute_resolved_at')->nullable();
            $table->foreignId('resolved_by_admin_id')->nullable()->constrained('users');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_suspended', 'suspended_at', 'suspension_reason']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['is_reported', 'report_reason', 'report_status', 'is_hidden', 'admin_notes', 'admin_reviewed_at']);
        });

        Schema::table('collaboration_requests', function (Blueprint $table) {
            $table->dropColumn(['is_reported', 'report_reason', 'is_hidden', 'admin_notes', 'admin_reviewed_at']);
        });

        Schema::table('stripe_transactions', function (Blueprint $table) {
            $table->dropColumn(['has_dispute', 'dispute_status', 'dispute_reason', 'dispute_resolution', 'dispute_resolved_at']);
            $table->dropForeign(['resolved_by_admin_id']);
            $table->dropColumn('resolved_by_admin_id');
        });
    }
}; 
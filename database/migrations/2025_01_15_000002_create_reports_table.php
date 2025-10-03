<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->onDelete('cascade');
            $table->enum('reportable_type', ['collaboration_request', 'user', 'message', 'review', 'application']);
            $table->unsignedBigInteger('reportable_id');
            $table->enum('reason', ['spam', 'scam', 'offensive', 'fake_opportunity', 'inappropriate', 'harassment', 'other'])->default('other');
            $table->text('comment')->nullable();
            $table->enum('status', ['pending', 'under_review', 'approved', 'dismissed', 'resolved'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->enum('admin_action', ['none', 'warn', 'suspend', 'delete', 'hide'])->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            
            // Composite index for reportable polymorphic relationship
            $table->index(['reportable_type', 'reportable_id']);
            
            // Indexes for performance
            $table->index(['status', 'created_at']);
            $table->index(['reporter_id', 'status']);
            $table->index(['reason', 'status']);
            
            // Prevent duplicate reports from same user on same content
            $table->unique(['reporter_id', 'reportable_type', 'reportable_id'], 'unique_user_report');
        });
    }

    public function down()
    {
        Schema::dropIfExists('reports');
    }
}; 
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collaboration_request_id')->constrained()->onDelete('cascade');
            $table->foreignId('initiator_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('respondent_id')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['open', 'under_review', 'resolved', 'closed'])->default('open');
            $table->enum('type', ['payment', 'quality', 'deadline', 'communication', 'other'])->default('other');
            $table->text('description');
            $table->text('evidence')->nullable(); // JSON field for evidence files/links
            $table->enum('resolution', ['payout_to_requestor', 'refund_to_applicants', 'shared_fault', 'no_action'])->nullable();
            $table->text('admin_notes')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('auto_opened_at')->nullable(); // For auto-opened disputes
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['status', 'created_at']);
            $table->index(['collaboration_request_id', 'status']);
            $table->index(['initiator_id', 'status']);
            $table->index(['respondent_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('disputes');
    }
}; 
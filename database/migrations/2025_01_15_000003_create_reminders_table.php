<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collaboration_request_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['day_3', 'day_7', 'day_14', 'auto_dispute'])->default('day_3');
            $table->enum('status', ['pending', 'sent', 'cancelled', 'failed'])->default('pending');
            $table->timestamp('scheduled_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('message')->nullable();
            $table->json('metadata')->nullable(); // Additional data like reminder count, etc.
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['status', 'scheduled_at']);
            $table->index(['collaboration_request_id', 'type']);
            $table->index(['user_id', 'status']);
            $table->index(['type', 'status']);
            
            // Prevent duplicate reminders of same type for same collaboration
            $table->unique(['collaboration_request_id', 'user_id', 'type'], 'unique_reminder_per_type');
        });
    }

    public function down()
    {
        Schema::dropIfExists('reminders');
    }
}; 
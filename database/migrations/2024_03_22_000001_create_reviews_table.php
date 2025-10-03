<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collaboration_request_id')->constrained()->onDelete('cascade');
            $table->foreignId('reviewer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('reviewee_id')->constrained('users')->onDelete('cascade');
            $table->integer('rating')->unsigned()->comment('1-5 star rating');
            $table->text('comment')->nullable();
            $table->boolean('is_flagged')->default(false);
            $table->text('flag_reason')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->text('admin_notes')->nullable();
            $table->timestamp('admin_reviewed_at')->nullable();
            $table->timestamps();
            
            // Ensure one review per user per collaboration
            $table->unique(['collaboration_request_id', 'reviewer_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('reviews');
    }
}; 
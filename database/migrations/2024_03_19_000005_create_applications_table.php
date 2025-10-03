<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('collaboration_request_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['pending', 'accepted', 'rejected', 'withdrawn'])->default('pending');
            $table->string('payment_intent_id')->nullable();
            $table->string('payment_status')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();

            // Ensure one application per user per request
            $table->unique(['user_id', 'collaboration_request_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('applications');
    }
};

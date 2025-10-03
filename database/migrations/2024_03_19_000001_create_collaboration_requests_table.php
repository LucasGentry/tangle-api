<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('collaboration_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->json('categories')->nullable();
            $table->json('platforms')->nullable();
            $table->timestamp('deadline')->nullable();

            $table->string('location_type')->nullable();
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->decimal('colloborator_count', 10, 2)->nullable();
            $table->json('collaboration_images')->nullable();

            $table->decimal('application_fee', 10, 2)->nullable();

            $table->enum('status', ['Draft', 'Open', 'Reviewing Applicants', 'In Progress', 'Completed', 'Cancelled'])->default('Draft');
            $table->string('cancellation_reason')->nullable();
            $table->string('share_token')->unique()->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('collaboration_requests');
    }
}; 
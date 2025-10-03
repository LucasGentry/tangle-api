<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('admin_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->onDelete('cascade');
            $table->string('action_type');
            $table->json('action_data');
            $table->string('ip_address');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('admin_logs');
    }
}; 
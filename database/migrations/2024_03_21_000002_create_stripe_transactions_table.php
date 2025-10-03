<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('stripe_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('stripe_transfer_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->decimal('platform_fee', 10, 2);
            $table->string('currency')->default('usd');
            $table->string('status');
            $table->string('type'); // 'application_fee', 'collaboration_payment', etc.
            
            // Manually creating polymorphic columns
            $table->string('transactionable_type');
            $table->unsignedBigInteger('transactionable_id');
            
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index('stripe_payment_intent_id');
            $table->index('stripe_transfer_id');
            // Using a shorter custom name for the polymorphic index
            $table->index(['transactionable_type', 'transactionable_id'], 'poly_relation_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('stripe_transactions');
    }
}; 
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('stripe_account_id')->nullable();
            $table->boolean('charges_enabled')->default(false);
            $table->boolean('payouts_enabled')->default(false);
            $table->string('stripe_account_status')->nullable();
            $table->timestamp('stripe_onboarding_completed_at')->nullable();
            $table->json('stripe_account_details')->nullable();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_account_id',
                'charges_enabled',
                'payouts_enabled',
                'stripe_account_status',
                'stripe_onboarding_completed_at',
                'stripe_account_details'
            ]);
        });
    }
}; 
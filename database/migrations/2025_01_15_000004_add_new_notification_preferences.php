<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            // Dispute notifications
            $table->boolean('dispute_events')->default(true);
            
            // Report notifications
            $table->boolean('report_events')->default(true);
            
            // Reminder notifications
            $table->boolean('reminder_events')->default(true);
            
            // Admin notifications
            $table->boolean('admin_events')->default(true);
        });
    }

    public function down()
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropColumn([
                'dispute_events',
                'report_events',
                'reminder_events',
                'admin_events'
            ]);
        });
    }
}; 
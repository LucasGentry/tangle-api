<?php

namespace App\Console\Commands;

use App\Models\CustomNotification;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class RetryFailedNotifications extends Command
{
    protected $signature = 'notifications:retry';
    protected $description = 'Retry failed notifications';

    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    public function handle()
    {
        $failedNotifications = CustomNotification::where('status', 'failed')
            ->where('retry_count', '<', 3)
            ->get();

        $count = 0;
        foreach ($failedNotifications as $notification) {
            try {
                $this->notificationService->processNotification($notification);
                $count++;
            } catch (\Exception $e) {
                $this->error("Failed to process notification {$notification->id}: {$e->getMessage()}");
            }
        }

        $this->info("Successfully retried {$count} notifications");
    }
} 
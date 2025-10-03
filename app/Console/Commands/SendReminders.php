<?php

namespace App\Console\Commands;

use App\Models\Reminder;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendReminders extends Command
{
    protected $signature = 'reminders:send {--type= : Specific reminder type to send}';
    protected $description = 'Send due reminders to users';

    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    public function handle()
    {
        $this->info('Starting reminder sending process...');

        $query = Reminder::due()->with(['collaborationRequest', 'user']);
        
        if ($type = $this->option('type')) {
            $query->where('type', $type);
        }

        $dueReminders = $query->get();
        
        if ($dueReminders->isEmpty()) {
            $this->info('No due reminders found.');
            return 0;
        }

        $this->info("Found {$dueReminders->count()} due reminders.");

        $sentCount = 0;
        $failedCount = 0;

        foreach ($dueReminders as $reminder) {
            try {
                $this->sendReminder($reminder);
                $sentCount++;
                $this->line("✓ Sent reminder {$reminder->id} to {$reminder->user->name}");
            } catch (\Exception $e) {
                $failedCount++;
                $reminder->markAsFailed();
                Log::error('Failed to send reminder', [
                    'reminder_id' => $reminder->id,
                    'error' => $e->getMessage()
                ]);
                $this->error("✗ Failed to send reminder {$reminder->id}: {$e->getMessage()}");
            }
        }

        $this->info("Reminder sending completed:");
        $this->info("- Sent: {$sentCount}");
        $this->info("- Failed: {$failedCount}");
        $this->info("- Total: {$dueReminders->count()}");

        return 0;
    }

    private function sendReminder(Reminder $reminder)
    {
        // Generate message if not set
        if (!$reminder->message) {
            $reminder->update(['message' => $reminder->default_message]);
        }

        // Send notification
        $this->notificationService->send(
            $reminder->user,
            'collaboration_reminder',
            [
                'reminder_id' => $reminder->id,
                'collaboration_title' => $reminder->collaborationRequest->title,
                'type' => $reminder->type,
                'message' => $reminder->message,
                'days_since_in_progress' => $reminder->days_since_in_progress
            ]
        );

        // Mark as sent
        $reminder->markAsSent();

        // If this is a day 14 reminder and collaboration is still in progress, auto-open dispute
        if ($reminder->type === Reminder::TYPE_DAY_14) {
            $this->handleAutoDispute($reminder);
        }
    }

    private function handleAutoDispute(Reminder $reminder)
    {
        $collaboration = $reminder->collaborationRequest;
        
        // Check if collaboration is still in progress
        if ($collaboration->status === \App\Models\CollaborationRequest::STATUS_IN_PROGRESS) {
            // Check if dispute already exists
            $existingDispute = \App\Models\Dispute::where('collaboration_request_id', $collaboration->id)
                ->whereIn('status', [\App\Models\Dispute::STATUS_OPEN, \App\Models\Dispute::STATUS_UNDER_REVIEW])
                ->first();

            if (!$existingDispute) {
                // Create auto-dispute
                \App\Models\Dispute::create([
                    'collaboration_request_id' => $collaboration->id,
                    'initiator_id' => $collaboration->user_id,
                    'respondent_id' => $reminder->user_id,
                    'type' => \App\Models\Dispute::TYPE_DEADLINE,
                    'description' => 'Auto-opened dispute due to collaboration not being completed within 14 days.',
                    'status' => \App\Models\Dispute::STATUS_OPEN,
                    'auto_opened_at' => now()
                ]);

                $this->line("Auto-opened dispute for collaboration {$collaboration->id}");

                // Send notification about auto-dispute
                $this->notificationService->send(
                    $reminder->user,
                    'auto_dispute_opened',
                    [
                        'collaboration_title' => $collaboration->title,
                        'dispute_type' => 'deadline'
                    ]
                );
            }
        }
    }
} 
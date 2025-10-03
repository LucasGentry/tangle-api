<?php

namespace App\Services;

use App\Models\User;
use App\Models\CustomNotification;
use App\Models\NotificationPreference;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Exception;

class NotificationService
{
    public function send(User $user, string $type, array $data, bool $shouldQueue = true)
    {
        try {
            $preferences = $user->notificationPreferences;

            if (!$preferences) {
                $preferences = NotificationPreference::create([
                    'user_id' => $user->id
                ]);
            }

            // Check if the user has enabled this type of notification
            if (!$this->isNotificationEnabled($preferences, $type)) {
                return;
            }

            // Create in-app notification if enabled
            if ($preferences->in_app_notifications) {
                $notification = CustomNotification::create([
                    'type' => $type,
                    'notifiable_type' => User::class,
                    'notifiable_id' => $user->id,
                    'data' => $data,
                    'status' => $shouldQueue ? 'pending' : 'sent'
                ]);

                if (!$shouldQueue) {
                    // Send immediately if not queued
                    $this->processNotification($notification);
                }
            }

            // Send email if enabled
            if ($preferences->email_notifications) {
                $this->sendEmail($user, $type, $data, $shouldQueue);
            }
        } catch (Exception $e) {
            Log::error('Failed to send notification: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'type' => $type,
                'data' => $data
            ]);
            throw $e;
        }
    }

    private function isNotificationEnabled(NotificationPreference $preferences, string $type): bool
    {
        $typeMap = [
            'new_application' => 'new_application',
            'application_accepted' => 'application_status',
            'application_rejected' => 'application_status',
            'application_withdrawn' => 'application_withdrawn',
            'request_closed' => 'request_status',
            'request_cancelled' => 'request_status',
            'collaboration_complete' => 'collaboration_complete',
            'new_message' => 'new_message',
            'stripe_connected' => 'stripe_account_status',
            'stripe_failed' => 'stripe_account_status',
            'payment_failed' => 'payment_status',
            'payout_initiated' => 'payout_status',
            'payout_failed' => 'payout_status',
            'payout_completed' => 'payout_status',
            'new_review' => 'new_review',
            'new_follower' => 'follow_events',
            // Dispute notifications
            'dispute_opened' => 'dispute_events',
            'dispute_response' => 'dispute_events',
            'dispute_resolved' => 'dispute_events',
            'dispute_closed' => 'dispute_events',
            'auto_dispute_opened' => 'dispute_events',
            // Report notifications
            'report_reviewed' => 'report_events',
            // Reminder notifications
            'collaboration_reminder' => 'reminder_events',
            // Admin notifications
            'admin_warning' => 'admin_events',
            'account_suspended' => 'admin_events',
        ];

        return $preferences->{$typeMap[$type] ?? $type} ?? true;
    }

    private function processNotification(CustomNotification $notification)
    {
        try {
            // Process the notification based on type
            // Add your notification processing logic here
            
            $notification->update(['status' => 'sent']);
        } catch (Exception $e) {
            $notification->update(['status' => 'failed']);
            $notification->incrementRetryCount();
            throw $e;
        }
    }

    private function sendEmail(User $user, string $type, array $data, bool $shouldQueue)
    {
        // Implement email sending logic here
        // You can create specific email classes for each notification type
        // Example:
        // if ($shouldQueue) {
        //     Mail::to($user)->queue(new NotificationEmail($type, $data));
        // } else {
        //     Mail::to($user)->send(new NotificationEmail($type, $data));
        // }
    }

    public function markAsRead(CustomNotification $notification)
    {
        $notification->markAsRead();
    }

    public function markAllAsRead(User $user)
    {
        CustomNotification::where('notifiable_id', $user->id)
            ->where('notifiable_type', User::class)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function getUnreadCount(User $user): int
    {
        return CustomNotification::where('notifiable_id', $user->id)
            ->where('notifiable_type', User::class)
            ->whereNull('read_at')
            ->count();
    }
} 
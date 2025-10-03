<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

abstract class BaseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function via($notifiable)
    {
        $channels = [];
        $preferences = $notifiable->notificationPreferences;

        if ($preferences->in_app_notifications) {
            $channels[] = 'database';
        }

        if ($preferences->email_notifications) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    abstract public function toMail($notifiable);
    abstract public function toArray($notifiable);
} 
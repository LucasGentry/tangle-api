<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class NewApplicationNotification extends BaseNotification
{
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('New Application Received')
            ->line('You have received a new application.')
            ->line('Application details:')
            ->line('From: ' . $this->data['applicant_name'])
            ->line('Project: ' . $this->data['project_name'])
            ->action('View Application', url('/applications/' . $this->data['application_id']))
            ->line('Thank you for using our platform!');
    }

    public function toArray($notifiable)
    {
        return [
            'type' => 'new_application',
            'application_id' => $this->data['application_id'],
            'applicant_name' => $this->data['applicant_name'],
            'project_name' => $this->data['project_name'],
            'message' => 'You have received a new application from ' . $this->data['applicant_name']
        ];
    }
} 
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'collaboration_requests',
        'messages',
        'application_updates',
        'marketing_emails',
    ];

    protected $casts = [
        'collaboration_requests' => 'boolean',
        'messages' => 'boolean',
        'application_updates' => 'boolean',
        'marketing_emails' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
} 
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CustomNotification extends Model
{
    use HasUuids;

    protected $fillable = [
        'type',
        'notifiable_type',
        'notifiable_id',
        'data',
        'read_at',
        'status',
        'retry_count',
        'last_retry_at'
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
        'last_retry_at' => 'datetime',
    ];

    public function notifiable()
    {
        return $this->morphTo();
    }

    public function markAsRead()
    {
        $this->update(['read_at' => now()]);
    }

    public function markAsUnread()
    {
        $this->update(['read_at' => null]);
    }

    public function incrementRetryCount()
    {
        $this->update([
            'retry_count' => $this->retry_count + 1,
            'last_retry_at' => now()
        ]);
    }
} 
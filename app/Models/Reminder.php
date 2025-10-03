<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Reminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'collaboration_request_id',
        'user_id',
        'type',
        'status',
        'scheduled_at',
        'sent_at',
        'cancelled_at',
        'message',
        'metadata'
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Type constants
    const TYPE_DAY_3 = 'day_3';
    const TYPE_DAY_7 = 'day_7';
    const TYPE_DAY_14 = 'day_14';
    const TYPE_AUTO_DISPUTE = 'auto_dispute';

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_FAILED = 'failed';

    public function collaborationRequest()
    {
        return $this->belongsTo(CollaborationRequest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeDue($query)
    {
        return $query->where('status', self::STATUS_PENDING)
                    ->where('scheduled_at', '<=', now());
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForCollaboration($query, $collaborationId)
    {
        return $query->where('collaboration_request_id', $collaborationId);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeSent($query)
    {
        return $query->where('status', self::STATUS_SENT);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isSent()
    {
        return $this->status === self::STATUS_SENT;
    }

    public function isCancelled()
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isFailed()
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isDue()
    {
        return $this->isPending() && $this->scheduled_at <= now();
    }

    public function markAsSent()
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'sent_at' => now()
        ]);
    }

    public function cancel()
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancelled_at' => now()
        ]);
    }

    public function markAsFailed()
    {
        $this->update([
            'status' => self::STATUS_FAILED
        ]);
    }

    public function getTypeLabelAttribute()
    {
        return ucwords(str_replace('_', ' ', $this->type));
    }

    public function getStatusLabelAttribute()
    {
        return ucfirst($this->status);
    }

    public function getDaysSinceInProgressAttribute()
    {
        if (!$this->collaborationRequest) return 0;
        
        $inProgressDate = $this->collaborationRequest->updated_at;
        return now()->diffInDays($inProgressDate);
    }

    public function getDefaultMessageAttribute()
    {
        $collaboration = $this->collaborationRequest;
        $days = $this->getDaysSinceInProgressAttribute();
        
        $messages = [
            self::TYPE_DAY_3 => "Hi {$this->user->name}! It's been 3 days since your collaboration '{$collaboration->title}' was marked as in progress. How's it going?",
            self::TYPE_DAY_7 => "Hi {$this->user->name}! It's been a week since your collaboration '{$collaboration->title}' was started. Any updates on the progress?",
            self::TYPE_DAY_14 => "Hi {$this->user->name}! It's been 14 days since your collaboration '{$collaboration->title}' began. Please provide an update on the current status.",
            self::TYPE_AUTO_DISPUTE => "Hi {$this->user->name}! Your collaboration '{$collaboration->title}' has been in progress for 14 days without completion. A dispute has been automatically opened to help resolve any issues."
        ];

        return $messages[$this->type] ?? "Reminder for collaboration: {$collaboration->title}";
    }

    public static function createForCollaboration($collaborationId, $userId, $type, $scheduledAt = null)
    {
        // Calculate scheduled time based on type if not provided
        if (!$scheduledAt) {
            $scheduledAt = self::calculateScheduledTime($type);
        }

        return self::create([
            'collaboration_request_id' => $collaborationId,
            'user_id' => $userId,
            'type' => $type,
            'scheduled_at' => $scheduledAt,
            'message' => null // Will be generated when sent
        ]);
    }

    public static function calculateScheduledTime($type)
    {
        $baseTime = now();
        
        return match($type) {
            self::TYPE_DAY_3 => $baseTime->addDays(3),
            self::TYPE_DAY_7 => $baseTime->addDays(7),
            self::TYPE_DAY_14 => $baseTime->addDays(14),
            self::TYPE_AUTO_DISPUTE => $baseTime->addDays(14),
            default => $baseTime->addDays(3)
        };
    }

    public static function scheduleAllReminders($collaborationId, $userId)
    {
        $reminders = [];
        
        // Schedule day 3, 7, and 14 reminders
        foreach ([self::TYPE_DAY_3, self::TYPE_DAY_7, self::TYPE_DAY_14] as $type) {
            $reminders[] = self::createForCollaboration($collaborationId, $userId, $type);
        }
        
        return $reminders;
    }

    public static function cancelAllForCollaboration($collaborationId)
    {
        return self::where('collaboration_request_id', $collaborationId)
                  ->where('status', self::STATUS_PENDING)
                  ->update([
                      'status' => self::STATUS_CANCELLED,
                      'cancelled_at' => now()
                  ]);
    }
} 
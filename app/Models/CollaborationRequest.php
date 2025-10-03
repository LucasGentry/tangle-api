<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class CollaborationRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'description', 'deadline', 'application_fee', 
        'location', 'status', 'user_id', 'share_token', 'categories', 'platforms', 'location_type', 'collaboration_images',
        'collaborator_count'
    ];

    protected $casts = [
        'categories' => 'array',
        'platforms' => 'array',
        'deadline' => 'datetime',
        'application_fee' => 'decimal:2',
        'collaboration_images' => 'array',
    ];

    // Status constants
    const STATUS_DRAFT = 'Draft';
    const STATUS_OPEN = 'Open';
    const STATUS_REVIEWING = 'Reviewing Applicants';
    const STATUS_IN_PROGRESS = 'In Progress';
    const STATUS_COMPLETED = 'Completed';
    const STATUS_CANCELLED = 'Cancelled';

    public static function boot()
    {
        parent::boot();
        
        static::creating(function ($request) {
            if (!$request->share_token) {
                $request->share_token = Str::random(32);
            }
        });

        // Schedule reminders when collaboration goes in progress
        static::updated(function ($collaboration) {
            if ($collaboration->wasChanged('status') && 
                $collaboration->status === self::STATUS_IN_PROGRESS) {
                // Schedule reminders for the collaboration owner
                Reminder::scheduleAllReminders($collaboration->id, $collaboration->user_id);
            }

            // Cancel reminders when collaboration is completed or cancelled
            if ($collaboration->wasChanged('status') && 
                in_array($collaboration->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED])) {
                Reminder::cancelAllForCollaboration($collaboration->id);
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    public function disputes()
    {
        return $this->hasMany(Dispute::class);
    }

    public function reports()
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    public function reminders()
    {
        return $this->hasMany(Reminder::class);
    }

    public function getIsEditableAttribute()
    {
        return $this->applications()->count() === 0 && 
               in_array($this->status, [self::STATUS_DRAFT, self::STATUS_OPEN]);
    }

    public function checkAndAutoClose()
    {
        if ($this->status === self::STATUS_OPEN && 
            $this->deadline && 
            now()->greaterThan($this->deadline)) {
            $this->status = self::STATUS_CANCELLED;
            $this->save();
            return true;
        }
        return false;
    }

    public function getShareUrlAttribute()
    {
        return config('app.url') . '/collaborations/' . $this->share_token;
    }

    public function scopeWithAdPlaceholders($query, $adsInterval = 6)
    {
        return $query->get()->map(function ($item, $index) use ($adsInterval) {
            if (($index + 1) % $adsInterval === 0) {
                $item->shouldShowAd = true;
                $item->adPosition = $index + 1;
            }
            return $item;
        });
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            self::STATUS_OPEN,
            self::STATUS_REVIEWING,
            self::STATUS_IN_PROGRESS
        ]);
    }

    public function canTransitionTo($newStatus)
    {
        $allowedTransitions = [
            self::STATUS_DRAFT => [self::STATUS_OPEN],
            self::STATUS_OPEN => [self::STATUS_REVIEWING, self::STATUS_CANCELLED],
            self::STATUS_REVIEWING => [self::STATUS_IN_PROGRESS, self::STATUS_CANCELLED],
            self::STATUS_IN_PROGRESS => [self::STATUS_COMPLETED, self::STATUS_CANCELLED],
            self::STATUS_COMPLETED => [],
            self::STATUS_CANCELLED => []
        ];

        return in_array($newStatus, $allowedTransitions[$this->status] ?? []);
    }

    public function hasOpenDisputes()
    {
        return $this->disputes()->open()->exists();
    }

    public function getOpenDisputesCountAttribute()
    {
        return $this->disputes()->open()->count();
    }

    public function getReportsCountAttribute()
    {
        return $this->reports()->count();
    }

    public function getPendingRemindersCountAttribute()
    {
        return $this->reminders()->pending()->count();
    }
}

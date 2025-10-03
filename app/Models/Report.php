<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'reporter_id',
        'reportable_type',
        'reportable_id',
        'reason',
        'comment',
        'status',
        'admin_notes',
        'admin_action',
        'reviewed_by',
        'reviewed_at'
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_UNDER_REVIEW = 'under_review';
    const STATUS_APPROVED = 'approved';
    const STATUS_DISMISSED = 'dismissed';
    const STATUS_RESOLVED = 'resolved';

    // Reason constants
    const REASON_SPAM = 'spam';
    const REASON_SCAM = 'scam';
    const REASON_OFFENSIVE = 'offensive';
    const REASON_FAKE_OPPORTUNITY = 'fake_opportunity';
    const REASON_INAPPROPRIATE = 'inappropriate';
    const REASON_HARASSMENT = 'harassment';
    const REASON_OTHER = 'other';

    // Admin action constants
    const ACTION_NONE = 'none';
    const ACTION_WARN = 'warn';
    const ACTION_SUSPEND = 'suspend';
    const ACTION_DELETE = 'delete';
    const ACTION_HIDE = 'hide';

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reportable()
    {
        return $this->morphTo();
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeUnderReview($query)
    {
        return $query->where('status', self::STATUS_UNDER_REVIEW);
    }

    public function scopeResolved($query)
    {
        return $query->whereIn('status', [self::STATUS_APPROVED, self::STATUS_DISMISSED, self::STATUS_RESOLVED]);
    }

    public function scopeByReason($query, $reason)
    {
        return $query->where('reason', $reason);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('reportable_type', $type);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('reporter_id', $userId);
    }

    public function scopeAgainstUser($query, $userId)
    {
        return $query->where('reportable_type', 'user')
                    ->where('reportable_id', $userId);
    }

    public function canBeReviewed()
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_UNDER_REVIEW]);
    }

    public function canTransitionTo($newStatus)
    {
        $allowedTransitions = [
            self::STATUS_PENDING => [self::STATUS_UNDER_REVIEW, self::STATUS_APPROVED, self::STATUS_DISMISSED],
            self::STATUS_UNDER_REVIEW => [self::STATUS_APPROVED, self::STATUS_DISMISSED, self::STATUS_RESOLVED],
            self::STATUS_APPROVED => [self::STATUS_RESOLVED],
            self::STATUS_DISMISSED => [self::STATUS_RESOLVED],
            self::STATUS_RESOLVED => []
        ];

        return in_array($newStatus, $allowedTransitions[$this->status] ?? []);
    }

    public function markAsUnderReview()
    {
        if ($this->canTransitionTo(self::STATUS_UNDER_REVIEW)) {
            $this->update(['status' => self::STATUS_UNDER_REVIEW]);
            return true;
        }
        return false;
    }

    public function approve($adminNotes = null, $adminAction = null, $adminId = null)
    {
        if (!$this->canTransitionTo(self::STATUS_APPROVED)) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_APPROVED,
            'admin_notes' => $adminNotes,
            'admin_action' => $adminAction,
            'reviewed_by' => $adminId ?? Auth::id(),
            'reviewed_at' => now()
        ]);

        return true;
    }

    public function dismiss($adminNotes = null, $adminId = null)
    {
        if (!$this->canTransitionTo(self::STATUS_DISMISSED)) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_DISMISSED,
            'admin_notes' => $adminNotes,
            'reviewed_by' => $adminId ?? Auth::id(),
            'reviewed_at' => now()
        ]);

        return true;
    }

    public function resolve($adminId = null)
    {
        if (!$this->canTransitionTo(self::STATUS_RESOLVED)) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_RESOLVED,
            'reviewed_by' => $adminId ?? Auth::id(),
            'reviewed_at' => now()
        ]);

        return true;
    }

    public function getStatusLabelAttribute()
    {
        return ucfirst(str_replace('_', ' ', $this->status));
    }

    public function getReasonLabelAttribute()
    {
        return ucfirst(str_replace('_', ' ', $this->reason));
    }

    public function getAdminActionLabelAttribute()
    {
        if (!$this->admin_action) return null;
        return ucfirst($this->admin_action);
    }

    public function getReportableTypeLabelAttribute()
    {
        return ucwords(str_replace('_', ' ', $this->reportable_type));
    }

    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isUnderReview()
    {
        return $this->status === self::STATUS_UNDER_REVIEW;
    }

    public function isResolved()
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_DISMISSED, self::STATUS_RESOLVED]);
    }

    public function isApproved()
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isDismissed()
    {
        return $this->status === self::STATUS_DISMISSED;
    }
} 
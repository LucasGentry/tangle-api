<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Dispute extends Model
{
    use HasFactory;

    protected $fillable = [
        'collaboration_request_id',
        'initiator_id',
        'respondent_id',
        'status',
        'type',
        'description',
        'evidence',
        'resolution',
        'admin_notes',
        'resolution_notes',
        'resolved_by',
        'resolved_at',
        'auto_opened_at'
    ];

    protected $casts = [
        'evidence' => 'array',
        'resolved_at' => 'datetime',
        'auto_opened_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Status constants
    const STATUS_OPEN = 'open';
    const STATUS_UNDER_REVIEW = 'under_review';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_CLOSED = 'closed';

    // Type constants
    const TYPE_PAYMENT = 'payment';
    const TYPE_QUALITY = 'quality';
    const TYPE_DEADLINE = 'deadline';
    const TYPE_COMMUNICATION = 'communication';
    const TYPE_OTHER = 'other';

    // Resolution constants
    const RESOLUTION_PAYOUT_TO_REQUESTOR = 'payout_to_requestor';
    const RESOLUTION_REFUND_TO_APPLICANTS = 'refund_to_applicants';
    const RESOLUTION_SHARED_FAULT = 'shared_fault';
    const RESOLUTION_NO_ACTION = 'no_action';

    public function collaborationRequest()
    {
        return $this->belongsTo(CollaborationRequest::class);
    }

    public function initiator()
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    public function respondent()
    {
        return $this->belongsTo(User::class, 'respondent_id');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_UNDER_REVIEW]);
    }

    public function scopeResolved($query)
    {
        return $query->whereIn('status', [self::STATUS_RESOLVED, self::STATUS_CLOSED]);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('initiator_id', $userId)
              ->orWhere('respondent_id', $userId);
        });
    }

    public function scopeAutoOpened($query)
    {
        return $query->whereNotNull('auto_opened_at');
    }

    public function scopeManuallyOpened($query)
    {
        return $query->whereNull('auto_opened_at');
    }

    public function isInvolved($userId)
    {
        return $this->initiator_id === $userId || $this->respondent_id === $userId;
    }

    public function canBeResolved()
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_UNDER_REVIEW]);
    }

    public function canTransitionTo($newStatus)
    {
        $allowedTransitions = [
            self::STATUS_OPEN => [self::STATUS_UNDER_REVIEW, self::STATUS_RESOLVED, self::STATUS_CLOSED],
            self::STATUS_UNDER_REVIEW => [self::STATUS_RESOLVED, self::STATUS_CLOSED],
            self::STATUS_RESOLVED => [self::STATUS_CLOSED],
            self::STATUS_CLOSED => []
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

    public function resolve($resolution, $notes = null, $adminId = null)
    {
        if (!$this->canTransitionTo(self::STATUS_RESOLVED)) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_RESOLVED,
            'resolution' => $resolution,
            'resolution_notes' => $notes,
            'resolved_by' => $adminId ?? Auth::id(),
            'resolved_at' => now()
        ]);

        return true;
    }

    public function close()
    {
        if ($this->canTransitionTo(self::STATUS_CLOSED)) {
            $this->update(['status' => self::STATUS_CLOSED]);
            return true;
        }
        return false;
    }

    public function getStatusLabelAttribute()
    {
        return ucfirst(str_replace('_', ' ', $this->status));
    }

    public function getTypeLabelAttribute()
    {
        return ucfirst($this->type);
    }

    public function getResolutionLabelAttribute()
    {
        if (!$this->resolution) return null;
        return ucwords(str_replace('_', ' ', $this->resolution));
    }

    public function isAutoOpened()
    {
        return !is_null($this->auto_opened_at);
    }

    public function isManuallyOpened()
    {
        return is_null($this->auto_opened_at);
    }
} 
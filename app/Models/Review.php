<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = [
        'collaboration_request_id',
        'reviewer_id',
        'reviewee_id',
        'rating',
        'comment',
        'is_flagged',
        'flag_reason',
        'is_hidden',
        'admin_notes',
        'admin_reviewed_at'
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_flagged' => 'boolean',
        'is_hidden' => 'boolean',
        'admin_reviewed_at' => 'datetime',
    ];

    public function collaborationRequest(): BelongsTo
    {
        return $this->belongsTo(CollaborationRequest::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function reviewee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewee_id');
    }

    public function scopeVisible($query)
    {
        return $query->where('is_hidden', false);
    }

    public function scopeFlagged($query)
    {
        return $query->where('is_flagged', true);
    }
} 
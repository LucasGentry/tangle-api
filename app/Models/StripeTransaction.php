<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StripeTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'stripe_payment_intent_id',
        'stripe_transfer_id',
        'amount',
        'platform_fee',
        'currency',
        'status',
        'type',
        'metadata'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'metadata' => 'array'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactionable(): MorphTo
    {
        return $this->morphTo();
    }
} 
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminLog extends Model
{
    protected $fillable = [
        'admin_id',
        'action_type',
        'action_data',
        'ip_address'
    ];

    protected $casts = [
        'action_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
} 
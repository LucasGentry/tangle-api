<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Privacy_VisibilityControls extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'profile_visibility',
        'media_visibility',
        'social_accounts_visibility',
        'social_accounts_audience',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

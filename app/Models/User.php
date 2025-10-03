<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Contracts\Auth\CanResetPassword;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Relations\HasOne;


class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'first_name',
        'last_name',
        'username',
        'tagline',
        'location',
        'bio',
        'profile_photo',
        'portfolio_images',
        'social_links',
        'social_media',
        'is_verified',
        'is_admin',
        'stripe_account_id',
        'charges_enabled',
        'payouts_enabled',
        'stripe_account_status',
        'stripe_onboarding_completed_at',
        'stripe_account_details',
        'rating',
        'reviews_count',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'stripe_account_id',
        'stripe_account_details',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'social_links' => 'array',
        'social_media' => 'array',
        'portfolio_images' => 'array',
        'stripe_account_details' => 'array',
        'charges_enabled' => 'boolean',
        'payouts_enabled' => 'boolean',
        'is_verified' => 'boolean',
        'is_admin' => 'boolean',
        'stripe_onboarding_completed_at' => 'datetime',
        'rating' => 'decimal:2',
    ];

    public function socialAccounts()
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function collaborationRequests(): HasMany
    {
        return $this->hasMany(CollaborationRequest::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewee_id');
    }

    public function givenReviews(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewer_id');
    }

    public function followers()
    {
        return $this->belongsToMany(User::class, 'follows', 'following_id', 'follower_id')
            ->withTimestamps();
    }

    public function following()
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'following_id')
            ->withTimestamps();
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    public function isFollowing(User $user)
    {
        return $this->following()->where('following_id', $user->id)->exists();
    }

    public function follow(User $user)
    {
        if ($user->id === $this->id) {
            return false;
        }
        return $this->following()->syncWithoutDetaching([$user->id]);
    }

    public function unfollow(User $user)
    {
        return $this->following()->detach($user->id);
    }

    public function updateRating($newRating)
    {
        $this->reviews_count++;
        $this->rating = ($this->rating * ($this->reviews_count - 1) + $newRating) / $this->reviews_count;
        $this->save();
    }

    public function stripeTransactions()
    {
        return $this->hasMany(StripeTransaction::class);
    }

    public function requiresStripeOnboarding(): bool
    {
        return !$this->stripe_account_id || 
               !$this->charges_enabled || 
               !$this->payouts_enabled;
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function notificationPreferences(): HasOne
    {
        return $this->hasOne(NotificationPreference::class);
    }

    public function disputesAsInitiator()
    {
        return $this->hasMany(Dispute::class, 'initiator_id');
    }

    public function disputesAsRespondent()
    {
        return $this->hasMany(Dispute::class, 'respondent_id');
    }

    public function disputes()
    {
        return $this->disputesAsInitiator()->union($this->disputesAsRespondent());
    }

    public function reports()
    {
        return $this->hasMany(Report::class, 'reporter_id');
    }

    public function reportsAgainst()
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    public function reminders()
    {
        return $this->hasMany(Reminder::class);
    }

    public function adminLogs()
    {
        return $this->hasMany(AdminLog::class, 'admin_id');
    }

    public function paymentMethods()
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function getOpenDisputesCountAttribute()
    {
        return $this->disputesAsInitiator()->open()->count() + 
               $this->disputesAsRespondent()->open()->count();
    }

    public function getReportsCountAttribute()
    {
        return $this->reports()->count();
    }

    public function getReportsAgainstCountAttribute()
    {
        return $this->reportsAgainst()->count();
    }

    public function getPendingRemindersCountAttribute()
    {
        return $this->reminders()->pending()->count();
    }

    public function privacyVisibilityControls()
    {
        return $this->hasOne(Privacy_VisibilityControls::class);
    }
}

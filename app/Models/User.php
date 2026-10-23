<?php

namespace App\Models;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /**
     * Minimum time between verification emails to one account, in seconds.
     */
    public const VERIFICATION_EMAIL_INTERVAL = 900;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_USER = 'user';
    public const ROLE_MODERATOR = 'moderator';
    public const ROLE_ADMIN = 'admin';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_trusted' => 'boolean',
        ];
    }

    /**
     * Send the verification link, at most once every 15 minutes.
     *
     * Returns 0 when it was sent, or the seconds left before another is allowed.
     */
    public function sendVerificationEmailThrottled(): int
    {
        $key = 'verify-email:'.$this->getKey();

        if (RateLimiter::tooManyAttempts($key, 1)) {
            return RateLimiter::availableIn($key);
        }

        RateLimiter::hit($key, self::VERIFICATION_EMAIL_INTERVAL);
        $this->notify(new VerifyEmail);

        return 0;
    }

    /**
     * Laravel calls this on sign-up; it goes through the same throttle.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->sendVerificationEmailThrottled();
    }

    /**
     * Until the address is confirmed, the verification link is the only email
     * this site will send to it. Returning null tells Laravel not to send.
     */
    public function routeNotificationForMail(mixed $notification): ?string
    {
        return $this->hasVerifiedEmail() || $notification instanceof VerifyEmail ? $this->email : null;
    }

    /**
     * Get the files the user uploaded.
     */
    public function uploads(): HasMany
    {
        return $this->hasMany(FileVersion::class);
    }

    /**
     * Whether the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Whether the user may review uploads and reports.
     */
    public function canModerate(): bool
    {
        return in_array($this->role, [self::ROLE_MODERATOR, self::ROLE_ADMIN], true);
    }

    /**
     * Whether the user's uploads go live without review.
     */
    public function skipsReview(): bool
    {
        return $this->is_trusted || $this->canModerate();
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }
}

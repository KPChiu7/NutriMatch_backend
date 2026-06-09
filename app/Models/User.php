<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Central user model for all roles: admin, rnd, client.
 *
 * @property int    $id
 * @property string $role  admin|rnd|client
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string $password  bcrypt-hashed
 * @property bool   $is_active
 */
class User extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     * Password is intentionally excluded — always set via setPasswordAttribute or Hash::make.
     */
    protected $fillable = [
        'role',
        'first_name',
        'last_name',
        'email',
        'phone',
        'profile_photo',
        'is_active',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden from serialization.
     * This prevents password and remember_token from leaking into API responses.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
            'deleted_at'        => 'datetime',
        ];
    }

    // --------------------------------------------------------
    // Accessors
    // --------------------------------------------------------

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isRnd(): bool
    {
        return $this->role === 'rnd';
    }

    public function isClient(): bool
    {
        return $this->role === 'client';
    }

    // --------------------------------------------------------
    // Relationships
    // --------------------------------------------------------

    public function rndProfile(): HasOne
    {
        return $this->hasOne(RndProfile::class);
    }

    public function clientProfile(): HasOne
    {
        return $this->hasOne(ClientProfile::class);
    }

    public function clientHealthProfile(): HasOne
    {
        return $this->hasOne(ClientHealthProfile::class);
    }

    /** Relationships where this user is the RND. */
    public function rndRelationships(): HasMany
    {
        return $this->hasMany(RndClientRelationship::class, 'rnd_id');
    }

    /** Relationships where this user is the client. */
    public function clientRelationships(): HasMany
    {
        return $this->hasMany(RndClientRelationship::class, 'client_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class, 'client_id');
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class, 'recipient_id');
    }
}

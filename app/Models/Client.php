<?php

namespace App\Models;

use App\Notifications\ClientResetPasswordNotification;
use App\Services\ActivityLogService;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Client extends Authenticatable
{
    use BelongsToTenant, HasApiTokens, HasFactory, LogsActivity, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'active',
        'path_image',
        'address_storage_enabled',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'address_storage_enabled' => 'boolean',
            'email_verified_at' => 'datetime',
        ];
    }

    protected static $recordEvents = ['created', 'updated', 'deleted'];

    public function scopeActive($query)
    {
        return $query->where('active', 1);
    }

    public function getActivitylogOptions(): LogOptions
    {
        $activityLogService = new ActivityLogService($this);

        return LogOptions::defaults()
            ->logOnly($activityLogService->getLoggableAttributes());
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ClientResetPasswordNotification($token));
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function addresses()
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function verificationChallenges(): HasMany
    {
        return $this->hasMany(ClientVerificationChallenge::class);
    }

    public function sensitiveAccessGrants(): HasMany
    {
        return $this->hasMany(ClientSensitiveAccessGrant::class);
    }

    public function defaultAddress()
    {
        return $this->hasOne(CustomerAddress::class)
            ->where('is_default', true);
    }
}

<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use HasRoles;
    use Notifiable;
    use TwoFactorAuthenticatable;

    protected $guard_name = 'crm';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'max_chat_id',
        'password',

        'type',          // customer | employee
        'status',        // active | blocked
        'account_type',  // individual | organization

        'city_id',
        'delivery_address',

        'profile_photo_path',

        'personal_data_consent_at',
        'personal_data_consent_ip',
        'marketing_consent_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    protected $appends = [
        'profile_photo_url',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'personal_data_consent_at' => 'datetime',
            'marketing_consent_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function entities(): BelongsToMany
    {
        return $this->belongsToMany(Entity::class)
            ->withPivot([
                'role',
                'status',
                'is_primary',
            ])
            ->withTimestamps();
    }

    public function createdOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'created_by_user_id');
    }

    public function entityClassifications(): BelongsToMany
    {
        return $this->belongsToMany(EntityClassification::class, 'entity_classification_user')
            ->withTimestamps();
    }

    public function bankConnections(): HasMany
    {
        return $this->hasMany(BankConnection::class, 'connected_by');
    }

    public function bankPaymentDrafts(): HasMany
    {
        return $this->hasMany(BankPaymentOrderDraft::class, 'created_by');
    }
}

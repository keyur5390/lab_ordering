<?php

namespace App\Models;

use App\Enums\PatientRelationship;
use App\Enums\UserRole;
use App\Observers\UserObserver;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[ObservedBy(UserObserver::class)]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Default attribute values. Mirrors the column default so new instances have a role before refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => UserRole::Customer->value,
        'sms_opt_out' => false,
    ];

    /**
     * The attributes that are mass assignable. `role` is deliberately excluded:
     * it is only changed by trusted code (see the user:role command).
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
            'role' => UserRole::class,
            'sms_opt_out' => 'boolean',
        ];
    }

    /**
     * Mobile number for SMS: the phone on the customer's "Self" profile, when it is in international
     * format (+ and country code). TODO(client): confirm using the Self profile's phone.
     */
    public function routeNotificationForSms(): ?string
    {
        $phone = $this->patients()->where('relationship', PatientRelationship::Self)->value('phone');
        $phone = preg_replace('/[\s\-]/', '', (string) $phone);

        return preg_match('/^\+[1-9][0-9]{6,14}$/', $phone) ? $phone : null;
    }

    /**
     * People this customer books tests for, "Self" first.
     *
     * @return HasMany<Patient, $this>
     */
    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class)
            ->orderByRaw('CASE WHEN relationship = ? THEN 0 ELSE 1 END', [PatientRelationship::Self->value])
            ->orderBy('name');
    }

    /**
     * Addresses for home collection.
     *
     * @return HasMany<Address, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class)->orderBy('id');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest('id');
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }
}

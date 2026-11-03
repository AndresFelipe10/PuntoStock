<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\Tenant\TenantManager;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'must_change_password',
        'terms_accepted_at',
        'terms_accepted_ip',
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
            'is_superadmin' => 'boolean',
            'must_change_password' => 'boolean',
            'terms_accepted_at' => 'datetime',
        ];
    }

    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'business_user')
            ->withPivot('role_id', 'is_active')
            ->withTimestamps();
    }

    /**
     * Get the currently active business (tenant) or fallback to the first associated business.
     */
    public function getBusinessAttribute(): ?Business
    {
        return app(\App\Services\Tenant\TenantManager::class)->get()
            ?? $this->businesses()->first();
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'business_user')
            ->withPivot('business_id', 'is_active')
            ->withTimestamps();
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * Get the role of the user in a specific business.
     */
    public function roleInBusiness(Business $business): ?Role
    {
        $membership = $this->businesses()
            ->where('businesses.id', $business->id)
            ->wherePivot('is_active', true)
            ->first();

        if (! $membership) {
            return null;
        }

        return Role::find($membership->pivot->role_id);
    }

    /**
     * Check if user is an admin in a specific business.
     */
    public function isAdminOf(Business $business): bool
    {
        return $this->roleInBusiness($business)?->slug === Role::ROLE_ADMIN;
    }

    /**
     * Check if user is an employee in a specific business.
     */
    public function isEmployeeOf(Business $business): bool
    {
        return $this->roleInBusiness($business)?->slug === Role::ROLE_EMPLOYEE;
    }

    /**
     * Get user's role in the currently active tenant business.
     */
    public function currentRole(): ?Role
    {
        $tenant = app(TenantManager::class)->get();

        if (! $tenant) {
            return null;
        }

        return $this->roleInBusiness($tenant);
    }

    /**
     * Check if user is an admin in the currently active tenant business.
     */
    public function isCurrentAdmin(): bool
    {
        return $this->currentRole()?->slug === Role::ROLE_ADMIN;
    }

    /**
     * Alias for isCurrentAdmin in current active tenant business.
     */
    public function isAdmin(): bool
    {
        return $this->isCurrentAdmin();
    }

    /**
     * Check if user is an employee in the currently active tenant business.
     */
     public function isCurrentEmployee(): bool
     {
         return $this->currentRole()?->slug === Role::ROLE_EMPLOYEE;
     }

    /**
     * Check if user has a specific role in current tenant.
     */
    public function hasRole(string $role): bool
    {
        return $this->currentRole()?->slug === $role;
    }

    /**
     * Check if user has accepted the terms of service (Ley 527 de 1999).
     */
    public function hasAcceptedTerms(): bool
    {
        return ! is_null($this->terms_accepted_at);
    }
}

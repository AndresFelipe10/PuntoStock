<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Business extends Model
{
    use HasFactory;

    protected $table = 'businesses';

    protected $fillable = [
        'name',
        'business_type',
        'nit',
        'phone',
        'email',
        'address',
        'nightly_report_enabled',
        'subscription_starts_at',
        'subscription_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'nightly_report_enabled' => 'boolean',
            'subscription_starts_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
        ];
    }

    /**
     * Get the integer number of calendar days until subscription expiration
     * computed against America/Bogota timezone.
     */
    public function daysUntilExpiration(): ?int
    {
        if (! $this->subscription_ends_at) {
            return null;
        }

        $now = Carbon::now('America/Bogota')->startOfDay();
        $endsAt = $this->subscription_ends_at->copy()->timezone('America/Bogota')->startOfDay();

        return (int) $now->diffInDays($endsAt, false);
    }

    /**
     * Check if subscription expires in 3 or 2 days.
     */
    public function isExpiringSoon(): bool
    {
        $days = $this->daysUntilExpiration();

        return $days !== null && $days <= 3 && $days > 1;
    }

    /**
     * Check if subscription expires tomorrow or today.
     */
    public function isCriticalExpiring(): bool
    {
        $days = $this->daysUntilExpiration();

        return $days !== null && $days <= 1 && $days >= 0;
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && (bool) $this->is_active;
    }

    public function isRestaurant(): bool
    {
        return $this->business_type === 'restaurant';
    }

    public function isRetail(): bool
    {
        return $this->business_type === 'retail' || empty($this->business_type);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'business_user')
            ->withPivot('role_id', 'is_active')
            ->withTimestamps();
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }


    public function restaurantTables(): HasMany
    {
        return $this->hasMany(RestaurantTable::class);
    }

    public function restaurantOrders(): HasMany
    {
        return $this->hasMany(RestaurantOrder::class);
    }
}

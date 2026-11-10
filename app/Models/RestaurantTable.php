<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RestaurantTable extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'restaurant_tables';

    protected $fillable = [
        'name',
        'capacity',
        'status',
        'is_active',
    ];

    protected $attributes = [
        'capacity' => 4,
        'status' => 'available',
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(RestaurantOrder::class, 'table_id');
    }

    public function activeOrder(): HasOne
    {
        return $this->hasOne(RestaurantOrder::class, 'table_id')
            ->whereNotIn('status', ['billed', 'closed', 'cancelled'])
            ->latestOfMany();
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    public function isOccupied(): bool
    {
        return $this->status === 'occupied';
    }

    public function isBilled(): bool
    {
        return $this->status === 'billed';
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'available' => 'Disponible / Libre',
            'occupied' => 'Ocupada',
            'billed' => 'En Cobro / Pre-cuenta emitida',
            default => ucfirst($this->status),
        };
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'customers';

    protected $fillable = [
        'name',
        'document',
        'identification_number',
        'email',
        'phone',
        'address',
        'is_active',
    ];

    public function setDocumentAttribute($value): void
    {
        $this->attributes['document'] = $value;
        $this->attributes['identification_number'] = $value;
    }

    public function setIdentificationNumberAttribute($value): void
    {
        $this->attributes['document'] = $value;
        $this->attributes['identification_number'] = $value;
    }

    public function getDocumentAttribute($value): ?string
    {
        return $value ?? $this->attributes['identification_number'] ?? null;
    }

    public function getIdentificationNumberAttribute($value): ?string
    {
        return $value ?? $this->attributes['document'] ?? null;
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}

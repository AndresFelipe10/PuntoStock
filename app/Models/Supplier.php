<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'suppliers';

    protected $fillable = [
        'name',
        'identification_number',
        'contact_name',
        'email',
        'phone',
        'address',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Accessor de compatibilidad para NIT (columna identification_number).
     */
    public function getNitAttribute(): ?string
    {
        return $this->identification_number;
    }

    /**
     * Gastos y facturas de compra asociadas al proveedor.
     */
    public function expenses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Expense::class);
    }
}

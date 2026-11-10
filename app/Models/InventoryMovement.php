<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    use HasFactory, BelongsToTenant;

    public const TYPE_ENTRY = 'entry';
    public const TYPE_EXIT = 'exit';
    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $table = 'inventory_movements';

    protected $fillable = [
        'product_id',
        'user_id',
        'sale_id',
        'type',
        'quantity',
        'previous_stock',
        'new_stock',
        'reason',
        'movement_date',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'previous_stock' => 'float',
            'new_stock' => 'float',
            'movement_date' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'sales';

    protected $fillable = [
        'user_id',
        'customer_id',
        'customer_name',
        'customer_document',
        'restaurant_order_id',
        'order_type',
        'invoice_number',
        'sale_date',
        'subtotal',
        'discount',
        'discount_percentage',
        'delivery_fee',
        'service_fee',
        'tax_inc',
        'total',
        'payment_method',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'sale_date' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'discount_percentage' => 'decimal:2',
            'delivery_fee' => 'float',
            'service_fee' => 'decimal:2',
            'tax_inc' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function restaurantOrder(): BelongsTo
    {
        return $this->belongsTo(RestaurantOrder::class, 'restaurant_order_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(SaleDetail::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * Get human-readable label for the sale's payment method.
     */
    public function getPaymentMethodLabelAttribute(): string
    {
        if ($this->payment_method === 'mixed') {
            return 'Pago mixto';
        }

        return \App\Enums\PaymentMethod::tryFrom((string) $this->payment_method)?->label() ?? (string) $this->payment_method;
    }

    public function getTipAmountAttribute(): float
    {
        return (float) ($this->service_fee ?? 0);
    }

    public function getTaxAmountAttribute(): float
    {
        return (float) ($this->tax_inc ?? 0);
    }
}

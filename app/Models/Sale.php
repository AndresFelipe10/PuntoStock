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

    protected static function booted(): void
    {
        static::saving(function (Sale $sale) {
            $defaultName = config('sales.default_customer_name', 'CONSUMIDOR FINAL');
            $defaultDoc = config('sales.default_customer_document', '222222222222');

            // Detect if seller's name was erroneously passed as customer_name
            $sellerName = null;
            if ($sale->user_id) {
                $seller = $sale->relationLoaded('user') ? $sale->user : User::find($sale->user_id);
                $sellerName = $seller?->name;
            }

            $isSellerName = false;
            if ($sellerName && filled($sale->customer_name)) {
                $normCust = trim(mb_strtolower($sale->customer_name));
                $normSeller = trim(mb_strtolower($sellerName));
                $firstSeller = trim(mb_strtolower(explode(' ', $sellerName)[0] ?? ''));
                if ($normCust === $normSeller || ($firstSeller !== '' && $normCust === $firstSeller)) {
                    $isSellerName = true;
                }
            }

            if (
                $sale->customer_document === '222222222222' ||
                blank($sale->customer_name) ||
                $isSellerName ||
                strtoupper(trim((string) $sale->customer_name)) === 'CONSUMIDOR FINAL'
            ) {
                if (blank($sale->customer_id) || $sale->customer_document === '222222222222') {
                    $sale->customer_name = $defaultName;
                    $sale->customer_document = $defaultDoc;
                }
            }
        });
    }

    public function getDisplayCustomerNameAttribute(): string
    {
        $defaultName = config('sales.default_customer_name', 'CONSUMIDOR FINAL');

        $customerName = trim((string) ($this->customer_name ?? ''));

        $sellerName = $this->relationLoaded('user') && $this->user ? $this->user->name : ($this->user?->name ?? '');
        $isSeller = false;
        if (filled($sellerName) && filled($customerName)) {
            $normCust = trim(mb_strtolower($customerName));
            $normSeller = trim(mb_strtolower($sellerName));
            $firstSeller = trim(mb_strtolower(explode(' ', $sellerName)[0] ?? ''));
            if ($normCust === $normSeller || ($firstSeller !== '' && $normCust === $firstSeller)) {
                $isSeller = true;
            }
        }

        if ($isSeller || blank($customerName) || $this->customer_document === '222222222222' || strtoupper($customerName) === 'CONSUMIDOR FINAL') {
            return $defaultName;
        }

        return $customerName;
    }

    public function getDisplayCustomerDocumentAttribute(): string
    {
        if ($this->customer_document === '222222222222' || blank($this->customer_document)) {
            return config('sales.default_customer_document', '222222222222');
        }

        return $this->customer_document;
    }

    /**
     * Get human-readable label for the sale's payment method in Spanish.
     */
    public function getPaymentMethodLabelAttribute(): string
    {
        if ($this->payment_method === 'mixed') {
            return 'Pago mixto';
        }

        return \App\Enums\PaymentMethod::tryFrom((string) $this->payment_method)?->label() ?? (string) $this->payment_method;
    }

    public function getFormattedPaymentMethodAttribute(): string
    {
        return $this->payment_method_label;
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

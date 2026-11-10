<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalePayment extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'sale_payments';

    protected $fillable = [
        'sale_id',
        'method',
        'amount',
        'reference',
        'cash_received',
        'change_given',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'cash_received' => 'decimal:2',
            'change_given' => 'decimal:2',
            'method' => PaymentMethod::class,
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the human-readable label of the payment method.
     */
    public function getMethodLabelAttribute(): string
    {
        if ($this->method instanceof PaymentMethod) {
            return $this->method->label();
        }

        return PaymentMethod::tryFrom((string) $this->method)?->label() ?? (string) $this->method;
    }
}

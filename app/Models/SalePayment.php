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
     * Get the human-readable label of the payment method in Spanish.
     */
    public function getMethodLabelAttribute(): string
    {
        $raw = is_object($this->method) && property_exists($this->method, 'value')
            ? (string) $this->method->value
            : (string) $this->method;

        $norm = strtolower(trim($raw));
        return match ($norm) {
            'cash', 'efectivo' => 'Efectivo',
            'transfer', 'transferencia' => 'Transferencia',
            'card', 'tarjeta', 'debit', 'credit_card' => 'Tarjeta',
            'nequi' => 'Nequi',
            'daviplata' => 'Daviplata',
            'credit', 'credito' => 'Crédito',
            'mixed', 'multiple', 'split' => 'Mixto',
            default => filled($norm) ? ucfirst($norm) : 'Otro',
        };
    }
}

<?php

namespace App\Models;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $table = 'expenses';

    protected $fillable = [
        'supplier_id',
        'invoice_number',
        'issue_date',
        'due_date',
        'category',
        'description',
        'amount',
        'status',
        'paid_at',
        'payment_method',
        'attachment_path',
        'attachment_original_name',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'paid_at' => 'date',
            'amount' => 'decimal:2',
            'category' => ExpenseCategory::class,
            'payment_method' => PaymentMethod::class,
        ];
    }

    /**
     * Proveedor asociado (opcional).
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Usuario que registró el gasto.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Verifica si el gasto está vencido (pendiente y fecha de vencimiento menor a hoy).
     */
    public function getIsOverdueAttribute(): bool
    {
        if ($this->status !== 'pending' || $this->due_date === null) {
            return false;
        }

        return $this->due_date->startOfDay()->lt(Carbon::today());
    }

    /**
     * Retorna la etiqueta legible de la categoría.
     */
    public function getCategoryLabelAttribute(): string
    {
        if ($this->category instanceof ExpenseCategory) {
            return $this->category->label();
        }

        return ExpenseCategory::tryFrom((string) $this->category)?->label() ?? (string) $this->category;
    }

    /**
     * Retorna la etiqueta legible del método de pago.
     */
    public function getPaymentMethodLabelAttribute(): ?string
    {
        if ($this->payment_method instanceof PaymentMethod) {
            return $this->payment_method->label();
        }

        return PaymentMethod::tryFrom((string) $this->payment_method)?->label() ?? $this->payment_method;
    }

    /**
     * Scope para filtrar gastos vencidos.
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', 'pending')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', Carbon::today());
    }

    /**
     * Scope para filtrar gastos pendientes (incluye vencidos).
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope para filtrar gastos pagados.
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', 'paid');
    }
}

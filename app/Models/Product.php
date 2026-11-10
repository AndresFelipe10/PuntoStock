<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'products';

    protected $fillable = [
        'category_id',
        'product_type',
        'base_unit',
        'name',
        'description',
        'sku',
        'cost',
        'cost_price',
        'purchase_price',
        'price',
        'sale_price',
        'stock',
        'min_stock',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'stock' => 'float',
            'min_stock' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function getPurchasePriceAttribute(): ?float
    {
        return $this->cost_price !== null ? (float) $this->cost_price : null;
    }

    public function setPurchasePriceAttribute($value): void
    {
        $this->attributes['cost_price'] = $value;
    }

    public function getCostAttribute(): ?float
    {
        return $this->cost_price !== null ? (float) $this->cost_price : null;
    }

    public function setCostAttribute($value): void
    {
        $this->attributes['cost_price'] = $value;
    }

    public function getPriceAttribute(): ?float
    {
        return $this->sale_price !== null ? (float) $this->sale_price : null;
    }

    public function setPriceAttribute($value): void
    {
        $this->attributes['sale_price'] = $value;
    }

    public function isDish(): bool
    {
        return $this->product_type === 'dish';
    }

    public function isRawMaterial(): bool
    {
        return $this->product_type === 'raw_material';
    }

    public function isStandard(): bool
    {
        return $this->product_type === 'standard' || empty($this->product_type);
    }

    public function getFormattedStockAttribute(): string
    {
        return (float) $this->stock == (int) $this->stock
            ? (string) (int) $this->stock
            : rtrim(rtrim(number_format((float) $this->stock, 3, '.', ''), '0'), '.');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function saleDetails(): HasMany
    {
        return $this->hasMany(SaleDetail::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * Genera un SKU único, legible y secuencial aislado por negocio.
     */
    public static function generateUniqueSku(int $businessId, ?string $productType = 'standard'): string
    {
        $prefix = ($productType === 'dish') ? 'MNU-' : 'ART-';

        $query = static::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('sku', 'like', "{$prefix}%");

        if (\Illuminate\Support\Facades\DB::transactionLevel() > 0 && \Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            $query->lockForUpdate();
        }

        $lastSku = $query->orderByRaw('LENGTH(sku) DESC, sku DESC')->value('sku');

        if ($lastSku && preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $lastSku, $matches)) {
            $candidateNumber = ((int) $matches[1]) + 1;
        } else {
            $count = static::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->where('sku', 'like', "{$prefix}%")
                ->count();
            $candidateNumber = $count + 1;
        }

        do {
            $candidateSku = sprintf('%s%03d', $prefix, $candidateNumber);
            $exists = static::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->where('sku', $candidateSku)
                ->exists();
            $candidateNumber++;
        } while ($exists);

        return $candidateSku;
    }
}

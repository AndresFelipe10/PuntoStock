<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\RestaurantOrderItem;
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

    /**
     * Stock neto disponible considerando órdenes de restaurante abiertas en curso.
     */
    public function getAvailableStockAttribute(): float
    {
        if (array_key_exists('available_stock', $this->attributes)) {
            return (float) $this->attributes['available_stock'];
        }

        if (! $this->isStandard()) {
            return (float) ($this->stock ?? 0);
        }

        $businessId = $this->business_id ?? session('current_business_id') ?? auth()->user()?->business_id;
        if (! $businessId) {
            return (float) ($this->stock ?? 0);
        }

        $committed = (float) RestaurantOrderItem::where('product_id', $this->id)
            ->where('status', '!=', 'cancelled')
            ->where('payment_status', 'pending')
            ->whereHas('order', function ($q) use ($businessId) {
                $q->where('business_id', $businessId)
                    ->whereNotIn('status', ['billed', 'closed', 'cancelled'])
                    ->whereNull('sale_id');
            })
            ->sum('quantity');

        $net = max(0, round((float) $this->stock - $committed, 3));
        $this->attributes['available_stock'] = $net;

        return $net;
    }

    public function getFormattedAvailableStockAttribute(): string
    {
        $avail = $this->available_stock;
        return (float) $avail == (int) $avail
            ? (string) (int) $avail
            : rtrim(rtrim(number_format((float) $avail, 3, '.', ''), '0'), '.');
    }

    /**
     * Calcula y adjunta el stock disponible neto (descontando unidades comprometidas
     * en órdenes de restaurante abiertas) en una colección de productos en 1 sola consulta agregada (Sin N+1).
     *
     * @param \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection|array $products
     * @param int|null $businessId
     * @return \Illuminate\Support\Collection
     */
    public static function attachAvailableStock($products, ?int $businessId = null)
    {
        $collection = collect($products);
        if ($collection->isEmpty()) {
            return $collection;
        }

        $businessId = $businessId 
            ?? session('current_business_id') 
            ?? auth()->user()?->business_id 
            ?? $collection->first()?->business_id;

        $standardProducts = $collection->filter(function ($p) {
            return $p instanceof self 
                ? $p->isStandard() 
                : (($p->product_type ?? 'standard') === 'standard' || empty($p->product_type));
        });

        if ($standardProducts->isEmpty() || ! $businessId) {
            foreach ($collection as $p) {
                $stockVal = (float) ($p->stock ?? 0);
                if ($p instanceof self) {
                    $p->attributes['available_stock'] = $stockVal;
                }
                $p->available_stock = $stockVal;
            }
            return $collection;
        }

        $standardIds = $standardProducts->pluck('id')->filter()->unique()->all();

        $committedMap = [];
        if (! empty($standardIds)) {
            $committedMap = RestaurantOrderItem::whereIn('product_id', $standardIds)
                ->where('status', '!=', 'cancelled')
                ->where('payment_status', 'pending')
                ->whereHas('order', function ($q) use ($businessId) {
                    $q->where('business_id', $businessId)
                        ->whereNotIn('status', ['billed', 'closed', 'cancelled'])
                        ->whereNull('sale_id');
                })
                ->selectRaw('product_id, SUM(quantity) as committed_qty')
                ->groupBy('product_id')
                ->pluck('committed_qty', 'product_id')
                ->all();
        }

        foreach ($collection as $p) {
            $isStd = $p instanceof self 
                ? $p->isStandard() 
                : (($p->product_type ?? 'standard') === 'standard' || empty($p->product_type));

            if ($isStd) {
                $committed = (float) ($committedMap[$p->id] ?? 0);
                $net = max(0, round((float) $p->stock - $committed, 3));
                if ($p instanceof self) {
                    $p->attributes['available_stock'] = $net;
                }
                $p->available_stock = $net;
            } else {
                $stockVal = (float) ($p->stock ?? 0);
                if ($p instanceof self) {
                    $p->attributes['available_stock'] = $stockVal;
                }
                $p->available_stock = $stockVal;
            }
        }

        return $collection;
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

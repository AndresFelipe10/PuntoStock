<?php

namespace App\Models\Concerns;

use App\Models\Business;
use App\Models\Scopes\TenantScope;
use App\Services\Tenant\TenantManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    /**
     * Boot the BelongsToTenant trait.
     */
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model) {
            $tenantManager = app(TenantManager::class);

            // Blindaje multi-tenant: si hay un tenant activo en sesión, sobrescribe forzosamente
            // cualquier valor inyectado para garantizar aislamiento hermético.
            if ($tenantManager->hasTenant()) {
                $model->business_id = $tenantManager->id();
            }
        });
    }

    /**
     * Obtiene los atributos asignables en masa.
     * Si no hay un tenant activo en el manager (ej. seeders, CLI o configuración de fixtures en tests),
     * permite business_id para posibilitar la creación programática sin errores de asignación masiva.
     */
    public function getFillable(): array
    {
        $fillable = parent::getFillable();

        if (! app(TenantManager::class)->hasTenant()) {
            if (! in_array('business_id', $fillable, true)) {
                $fillable[] = 'business_id';
            }
        }

        return $fillable;
    }

    /**
     * Determina si el atributo puede asignarse masivamente.
     * Impide que business_id sea asignable masivamente cuando existe un tenant activo,
     * permitiendo asignación programática en seeders, comandos o tests sin tenant activo.
     */
    public function isFillable($key)
    {
        if ($key === 'business_id') {
            return ! app(TenantManager::class)->hasTenant();
        }

        return parent::isFillable($key);
    }

    /**
     * Get the business that owns this entity.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}

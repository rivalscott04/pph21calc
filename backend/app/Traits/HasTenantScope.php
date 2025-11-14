<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait HasTenantScope
{
    /**
     * Boot the trait
     */
    protected static function bootHasTenantScope()
    {
        // Add global scope to filter by tenant_id
        static::addGlobalScope('tenant', function (Builder $builder) {
            // If user is superadmin, don't apply tenant scope
            $user = auth()->user();
            if ($user && $user->isSuperadmin()) {
                return; // Don't apply tenant scope for superadmin
            }
            
            // Get tenant_id from request or app instance
            $tenantId = request()->input('tenant_id') 
                ?? (app()->bound('tenant_id') ? app('tenant_id') : null);
            
            // If tenant_id is set, apply scope
            if ($tenantId) {
                // Check if model wants to include global records (tenant_id IS NULL)
                // This is useful for models like IdentifierScheme that can have global schemes
                $includeGlobal = property_exists($builder->getModel(), 'includeGlobalTenantScope') 
                    && $builder->getModel()->includeGlobalTenantScope === true;
                
                if ($includeGlobal) {
                    // Include global records (tenant_id IS NULL) + tenant-specific records
                    $builder->where(function ($query) use ($tenantId) {
                        $query->where('tenant_id', $tenantId)
                              ->orWhereNull('tenant_id');
                    });
                } else {
                    // Only tenant-specific records
                    $builder->where('tenant_id', $tenantId);
                }
            }
        });
    }

    /**
     * Get tenant ID from request context
     */
    protected function getTenantId()
    {
        return request()->input('tenant_id') 
            ?? (app()->bound('tenant_id') ? app('tenant_id') : null);
    }

    /**
     * Scope a query to a specific tenant
     */
    public function scopeForTenant(Builder $query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope to bypass tenant filter (for superadmin)
     */
    public function scopeWithoutTenantScope(Builder $query)
    {
        return $query->withoutGlobalScope('tenant');
    }
}

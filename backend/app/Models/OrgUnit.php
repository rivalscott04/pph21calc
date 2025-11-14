<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrgUnit extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'type',
        'parent_id',
    ];

    /**
     * Get the tenant that owns the org unit
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get parent org unit
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'parent_id');
    }

    /**
     * Get child org units
     */
    public function children(): HasMany
    {
        return $this->hasMany(OrgUnit::class, 'parent_id');
    }

    /**
     * Get employments in this org unit
     */
    public function employments(): HasMany
    {
        return $this->hasMany(Employment::class);
    }
}

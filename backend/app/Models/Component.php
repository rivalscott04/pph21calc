<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Component extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'group',
        'taxable',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'taxable' => 'boolean',
        ];
    }

    /**
     * Get the tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get earnings using this component
     */
    public function earnings(): HasMany
    {
        return $this->hasMany(Earning::class);
    }
}

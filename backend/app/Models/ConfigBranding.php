<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfigBranding extends Model
{
    use HasTenantScope;

    protected $table = 'config_branding';

    protected $fillable = [
        'tenant_id',
        'primary',
        'secondary',
        'accent',
        'neutral',
        'base100',
    ];

    /**
     * Get the tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

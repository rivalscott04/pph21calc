<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollSubject extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'tenant_id',
        'employment_id',
        'ptkp_code',
        'has_npwp',
        'tax_profile_json',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'has_npwp' => 'boolean',
            'tax_profile_json' => 'array',
            'active' => 'boolean',
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
     * Get the employment
     */
    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }
}

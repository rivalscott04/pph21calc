<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfigModule extends Model
{
    use HasTenantScope;

    protected $table = 'config_modules';

    protected $fillable = [
        'tenant_id',
        'core_payroll',
        'coretax_integration',
        'compliance_ojk',
        'compliance_pdp',
        'audit_trail',
        'bpjs_integration',
        'syariah_extension',
    ];

    protected function casts(): array
    {
        return [
            'core_payroll' => 'boolean',
            'coretax_integration' => 'boolean',
            'compliance_ojk' => 'boolean',
            'compliance_pdp' => 'boolean',
            'audit_trail' => 'boolean',
            'bpjs_integration' => 'boolean',
            'syariah_extension' => 'boolean',
        ];
    }

    /**
     * Get the tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

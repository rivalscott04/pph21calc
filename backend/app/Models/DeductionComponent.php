<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeductionComponent extends Model
{
    use HasTenantScope, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'type',
        'calculation_type',
        'is_tax_deductible',
        'priority',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_tax_deductible' => 'boolean',
            'priority' => 'integer',
            'is_active' => 'boolean',
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
     * Get deductions using this component
     */
    public function deductions(): HasMany
    {
        return $this->hasMany(DeductionsManual::class);
    }
}

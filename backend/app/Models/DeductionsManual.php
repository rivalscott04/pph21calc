<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeductionsManual extends Model
{
    use HasTenantScope;

    protected $table = 'deductions_manual';

    protected $fillable = [
        'tenant_id',
        'employment_id',
        'period_id',
        'deduction_component_id',
        'type', // Keep for backward compatibility during migration
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
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

    /**
     * Get the period
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    /**
     * Get the deduction component
     */
    public function deductionComponent(): BelongsTo
    {
        return $this->belongsTo(DeductionComponent::class);
    }
}

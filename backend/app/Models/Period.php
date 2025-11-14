<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Period extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'tenant_id',
        'year',
        'month',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
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
     * Get earnings for this period
     */
    public function earnings(): HasMany
    {
        return $this->hasMany(Earning::class);
    }

    /**
     * Get deductions for this period
     */
    public function deductions(): HasMany
    {
        return $this->hasMany(DeductionsManual::class);
    }

    /**
     * Get payroll calculations for this period
     */
    public function payrollCalculations(): HasMany
    {
        return $this->hasMany(PayrollCalculation::class);
    }
}

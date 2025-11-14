<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollCalculation extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'tenant_id',
        'employment_id',
        'period_id',
        'bruto',
        'biaya_jabatan',
        'iuran_pensiun',
        'zakat',
        'neto_masa',
        'ptkp_yearly',
        'pkp_annualized',
        'pph21_masa',
        'pph21_ytd',
        'pph21_settlement_dec',
    ];

    protected function casts(): array
    {
        return [
            'bruto' => 'decimal:2',
            'biaya_jabatan' => 'decimal:2',
            'iuran_pensiun' => 'decimal:2',
            'zakat' => 'decimal:2',
            'neto_masa' => 'decimal:2',
            'ptkp_yearly' => 'decimal:2',
            'pkp_annualized' => 'decimal:2',
            'pph21_masa' => 'decimal:2',
            'pph21_ytd' => 'decimal:2',
            'pph21_settlement_dec' => 'decimal:2',
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
}

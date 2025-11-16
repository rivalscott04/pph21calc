<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalculationHistory extends Model
{
    use HasTenantScope;

    protected $table = 'calculation_history';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'employment_id',
        'person_name',
        'ptkp_code',
        'has_npwp',
        'year',
        'month',
        'bruto',
        'biaya_jabatan',
        'iuran_pensiun',
        'zakat',
        'neto_masa',
        'ptkp_yearly',
        'pkp_annualized',
        'pph21_masa',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'has_npwp' => 'boolean',
            'year' => 'integer',
            'month' => 'integer',
            'bruto' => 'decimal:2',
            'biaya_jabatan' => 'decimal:2',
            'iuran_pensiun' => 'decimal:2',
            'zakat' => 'decimal:2',
            'neto_masa' => 'decimal:2',
            'ptkp_yearly' => 'decimal:2',
            'pkp_annualized' => 'decimal:2',
            'pph21_masa' => 'decimal:2',
            'notes' => 'array',
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
     * Get the user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the employment
     */
    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }
}

<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoretaxLog extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'tenant_id',
        'period_id',
        'payload_json',
        'status',
        'ref_no',
        'response_json',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'response_json' => 'array',
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
     * Get the period
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }
}

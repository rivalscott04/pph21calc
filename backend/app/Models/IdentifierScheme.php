<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IdentifierScheme extends Model
{
    use HasTenantScope, LogsActivity;

    /**
     * Include global schemes (tenant_id IS NULL) in tenant scope
     */
    protected $includeGlobalTenantScope = true;

    protected $fillable = [
        'tenant_id',
        'code',
        'label',
        'prefix', // Prefix untuk ID (contoh: "NTB")
        'entity_type',
        'regex_pattern', // Auto-generate dari format_type
        'length_min', // Panjang bagian belakang (setelah prefix)
        'length_max', // Panjang bagian belakang (setelah prefix)
        'normalize_rule', // NUMERIC/ALNUM/UPPER/NONE
        'example', // Auto-generate
        'checksum_type', // Tetap ada untuk backward compatibility, default NONE
    ];

    protected function casts(): array
    {
        return [
            'length_min' => 'integer',
            'length_max' => 'integer',
        ];
    }

    /**
     * Get the tenant (nullable - can be global)
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get person identifiers using this scheme
     */
    public function personIdentifiers(): HasMany
    {
        return $this->hasMany(PersonIdentifier::class);
    }
}

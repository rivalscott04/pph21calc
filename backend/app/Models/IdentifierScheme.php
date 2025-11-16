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
        'entity_type',
        'regex_pattern',
        'length_min',
        'length_max',
        'normalize_rule',
        'example',
        'checksum_type',
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

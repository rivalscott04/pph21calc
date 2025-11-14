<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonIdentifier extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'tenant_id',
        'person_id',
        'scheme_id',
        'raw_value',
        'norm_value',
        'scope_entity_id',
        'scope_org_unit_id',
        'effective_start',
        'effective_end',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'effective_start' => 'date',
            'effective_end' => 'date',
            'is_primary' => 'boolean',
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
     * Get the person
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Get the identifier scheme
     */
    public function scheme(): BelongsTo
    {
        return $this->belongsTo(IdentifierScheme::class);
    }
}

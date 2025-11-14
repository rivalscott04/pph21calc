<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Person extends Model
{
    use HasTenantScope;

    protected $table = 'persons';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'full_name',
        'nik',
        'npwp',
        'birth_date',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }

    /**
     * Get the tenant that owns the person
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get person identifiers
     */
    public function identifiers(): HasMany
    {
        return $this->hasMany(PersonIdentifier::class);
    }

    /**
     * Get employments
     */
    public function employments(): HasMany
    {
        return $this->hasMany(Employment::class);
    }
}

<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employment extends Model
{
    use HasTenantScope, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'person_id',
        'org_unit_id',
        'employment_type',
        'start_date',
        'end_date',
        'primary_payroll',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'primary_payroll' => 'boolean',
        ];
    }

    /**
     * Get the tenant that owns the employment
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
     * Get the org unit
     */
    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    /**
     * Get payroll subject
     */
    public function payrollSubject(): HasOne
    {
        return $this->hasOne(PayrollSubject::class)->where('active', true);
    }

    /**
     * Get all payroll subjects
     */
    public function payrollSubjects(): HasMany
    {
        return $this->hasMany(PayrollSubject::class);
    }

    /**
     * Get earnings
     */
    public function earnings(): HasMany
    {
        return $this->hasMany(Earning::class);
    }

    /**
     * Get deductions
     */
    public function deductions(): HasMany
    {
        return $this->hasMany(DeductionsManual::class);
    }

    /**
     * Get payroll calculations
     */
    public function payrollCalculations(): HasMany
    {
        return $this->hasMany(PayrollCalculation::class);
    }
}

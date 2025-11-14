<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_superadmin',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_superadmin' => 'boolean',
        ];
    }

    /**
     * Get tenant users relationship
     */
    public function tenantUsers()
    {
        return $this->hasMany(TenantUser::class);
    }

    /**
     * Check if user is superadmin
     */
    public function isSuperadmin(): bool
    {
        return $this->is_superadmin === true;
    }

    /**
     * Get active tenant for user
     */
    public function getActiveTenant()
    {
        return $this->tenantUsers()
            ->where('status', 'active')
            ->with('tenant')
            ->first();
    }

    /**
     * Get user's role in a specific tenant
     */
    public function getRoleInTenant($tenantId)
    {
        $tenantUser = $this->tenantUsers()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first();

        return $tenantUser ? $tenantUser->role : null;
    }

    /**
     * Check if user has specific role in tenant
     */
    public function hasRoleInTenant($tenantId, $role): bool
    {
        return $this->getRoleInTenant($tenantId) === $role;
    }

    /**
     * Check if user has any of the specified roles in tenant
     */
    public function hasAnyRoleInTenant($tenantId, array $roles): bool
    {
        $userRole = $this->getRoleInTenant($tenantId);
        return $userRole && in_array($userRole, $roles);
    }
}

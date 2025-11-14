<?php

namespace App\Enums;

enum Role: string
{
    case SUPERADMIN = 'SUPERADMIN';
    case TENANT_ADMIN = 'TENANT_ADMIN';
    case HR = 'HR';
    case FINANCE = 'FINANCE';
    case VIEWER = 'VIEWER';

    /**
     * Get all tenant roles (excluding superadmin)
     */
    public static function tenantRoles(): array
    {
        return [
            self::TENANT_ADMIN->value,
            self::HR->value,
            self::FINANCE->value,
            self::VIEWER->value,
        ];
    }

    /**
     * Check if role is tenant role
     */
    public static function isTenantRole(string $role): bool
    {
        return in_array($role, self::tenantRoles());
    }

    /**
     * Get role label
     */
    public function label(): string
    {
        return match($this) {
            self::SUPERADMIN => 'Super Admin',
            self::TENANT_ADMIN => 'Tenant Admin',
            self::HR => 'HR / Payroll Officer',
            self::FINANCE => 'Finance / Tax Officer',
            self::VIEWER => 'Viewer / Auditor',
        };
    }
}


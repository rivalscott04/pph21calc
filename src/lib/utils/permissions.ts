import { get } from 'svelte/store';
import { auth } from '$lib/stores/auth.js';

export type Role = 'SUPERADMIN' | 'TENANT_ADMIN' | 'HR' | 'FINANCE' | 'VIEWER';

/**
 * Check if user is superadmin
 */
export function isSuperadmin(): boolean {
	const state = get(auth);
	return state.user?.is_superadmin ?? false;
}

/**
 * Get current user role
 */
export function getCurrentRole(): Role | null {
	const state = get(auth);
	if (isSuperadmin()) return 'SUPERADMIN';
	return (state.tenant?.role as Role) || null;
}

/**
 * Check if user has specific role
 */
export function hasRole(role: Role): boolean {
	if (isSuperadmin()) return true; // Superadmin bisa semua
	return getCurrentRole() === role;
}

/**
 * Check if user has any of the specified roles
 */
export function hasAnyRole(roles: Role[]): boolean {
	if (isSuperadmin()) return true; // Superadmin bisa semua
	const currentRole = getCurrentRole();
	return currentRole ? roles.includes(currentRole) : false;
}

/**
 * Check if user can access menu item
 */
export function canAccessMenu(menu: string): boolean {
	if (isSuperadmin()) return true; // Superadmin bisa semua
	
	const role = getCurrentRole();
	if (!role) return false;
	
	// Menu permissions mapping
	const menuPermissions: Record<string, Role[]> = {
		'dashboard': ['SUPERADMIN', 'TENANT_ADMIN', 'HR', 'FINANCE', 'VIEWER'],
		'payroll': ['SUPERADMIN', 'TENANT_ADMIN', 'HR', 'FINANCE'],
		'calculator': ['SUPERADMIN', 'TENANT_ADMIN', 'HR', 'FINANCE'],
		'coretax': ['SUPERADMIN', 'TENANT_ADMIN', 'FINANCE'],
		'master/persons': ['SUPERADMIN', 'TENANT_ADMIN', 'HR'],
		'settings/branding': ['SUPERADMIN', 'TENANT_ADMIN'],
		'settings/id-schemes': ['SUPERADMIN', 'TENANT_ADMIN'],
		'settings/modules': ['SUPERADMIN', 'TENANT_ADMIN'],
		'settings/users': ['SUPERADMIN', 'TENANT_ADMIN'],
	};
	
	const allowedRoles = menuPermissions[menu] || [];
	return allowedRoles.includes(role);
}



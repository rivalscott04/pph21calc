import { apiGet, apiPost, apiPatch } from './client.js';
import type { PaginatedResponse } from './persons.js';
import type { User } from '$lib/stores/auth.js';

export interface Tenant {
	id: number;
	code: string;
	name: string;
	status: 'active' | 'inactive';
	created_at: string;
	updated_at: string;
}

export interface TenantUser {
	id: number;
	user_id: number;
	tenant_id: number;
	role: string;
	status?: 'active' | 'inactive';
	user?: User;
}

export const tenantsApi = {
	async list(): Promise<Tenant[]> {
		return await apiGet<Tenant[]>('/tenants');
	},
	
	async get(id: number): Promise<Tenant> {
		return await apiGet<Tenant>(`/tenants/${id}`);
	},
	
	async create(tenant: {
		code: string;
		name: string;
		status?: 'active' | 'inactive';
	}): Promise<Tenant> {
		return await apiPost<Tenant>('/tenants', tenant);
	},
	
	async update(id: number, tenant: Partial<{
		code: string;
		name: string;
		status: 'active' | 'inactive';
	}>): Promise<Tenant> {
		return await apiPatch<Tenant>(`/tenants/${id}`, tenant);
	},
	
	async listUsers(tenantId: number): Promise<TenantUser[]> {
		return await apiGet<TenantUser[]>(`/tenants/${tenantId}/users`);
	},
	
	async createUser(tenantId: number, user: {
		name: string;
		email: string;
		password: string;
		role: string;
	}): Promise<TenantUser> {
		return await apiPost<TenantUser>(`/tenants/${tenantId}/users`, user);
	}
};





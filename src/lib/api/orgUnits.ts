import { apiGet, apiPost, apiPatch, apiDelete } from './client.js';
import type { PaginatedResponse } from './persons.js';

export interface OrgUnit {
	id: number;
	tenant_id: number;
	parent_id: number | null;
	code: string;
	name: string;
	type: string | null;
	description: string | null;
	created_at: string;
	updated_at: string;
	parent?: OrgUnit;
	children?: OrgUnit[];
}

export const orgUnitsApi = {
	async list(params?: {
		parent_id?: number | null;
		type?: string;
		search?: string;
		all?: boolean;
		per_page?: number;
	}): Promise<PaginatedResponse<OrgUnit>> {
		return await apiGet<PaginatedResponse<OrgUnit>>('/org-units', params);
	},
	
	async get(id: number): Promise<OrgUnit> {
		return await apiGet<OrgUnit>(`/org-units/${id}`);
	},
	
	async create(orgUnit: {
		code: string;
		name: string;
		parent_id?: number | null;
		type?: string | null;
		description?: string | null;
	}): Promise<OrgUnit> {
		return await apiPost<OrgUnit>('/org-units', orgUnit);
	},
	
	async update(id: number, orgUnit: Partial<{
		code: string;
		name: string;
		parent_id: number | null;
		type: string | null;
		description: string | null;
	}>): Promise<OrgUnit> {
		return await apiPatch<OrgUnit>(`/org-units/${id}`, orgUnit);
	},
	
	async delete(id: number): Promise<void> {
		return await apiDelete(`/org-units/${id}`);
	},
	
	async tree(params?: {
		type?: string;
	}): Promise<OrgUnit[]> {
		return await apiGet<OrgUnit[]>('/org-units/tree', params);
	}
};


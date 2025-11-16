import { apiGet, apiPost, apiPatch } from './client.js';
import type { PaginatedResponse } from './persons.js';

export interface Component {
	id: number;
	tenant_id: number;
	code: string;
	name: string;
	group: 'gaji_pokok' | 'tunjangan' | 'bonus' | 'lembur' | 'natura' | 'lainnya';
	taxable: boolean;
	is_mandatory?: boolean;
	priority?: number;
	is_active?: boolean;
	notes?: string | null;
	created_at: string;
	updated_at: string;
}

export const componentsApi = {
	async list(params?: {
		group?: string;
		taxable?: boolean;
		per_page?: number;
	}): Promise<PaginatedResponse<Component>> {
		return await apiGet<PaginatedResponse<Component>>('/components', params);
	},
	
	async get(id: number): Promise<Component> {
		return await apiGet<Component>(`/components/${id}`);
	},
	
	async create(component: {
		code: string;
		name: string;
		group: string;
		taxable: boolean;
		is_mandatory?: boolean;
		priority?: number;
		is_active?: boolean;
		notes?: string;
	}): Promise<Component> {
		return await apiPost<Component>('/components', component);
	},
	
	async update(id: number, component: Partial<{
		code: string;
		name: string;
		group: string;
		taxable: boolean;
		is_mandatory?: boolean;
		priority?: number;
		is_active?: boolean;
		notes?: string;
	}>): Promise<Component> {
		return await apiPatch<Component>(`/components/${id}`, component);
	}
};


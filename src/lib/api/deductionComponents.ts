import { apiGet, apiPost, apiPatch, apiDelete } from './client.js';
import type { PaginatedResponse } from './persons.js';

export interface DeductionComponent {
	id: number;
	tenant_id: number;
	code: string;
	name: string;
	type: 'mandatory' | 'custom';
	calculation_type: 'auto' | 'manual' | 'percentage';
	is_tax_deductible: boolean;
	priority: number;
	is_active: boolean;
	notes?: string | null;
	created_at: string;
	updated_at: string;
}

export const deductionComponentsApi = {
	async list(params?: {
		type?: string;
		calculation_type?: string;
		is_active?: boolean;
		is_tax_deductible?: boolean;
		per_page?: number;
		page?: number;
		search?: string;
	}): Promise<PaginatedResponse<DeductionComponent>> {
		return await apiGet<PaginatedResponse<DeductionComponent>>('/deduction-components', params);
	},
	
	async get(id: number): Promise<DeductionComponent> {
		return await apiGet<DeductionComponent>(`/deduction-components/${id}`);
	},
	
	async create(component: {
		code: string;
		name: string;
		type: string;
		calculation_type: string;
		is_tax_deductible: boolean;
		priority?: number;
		is_active?: boolean;
		notes?: string;
	}): Promise<DeductionComponent> {
		return await apiPost<DeductionComponent>('/deduction-components', component);
	},
	
	async update(id: number, component: Partial<{
		code: string;
		name: string;
		type: string;
		calculation_type: string;
		is_tax_deductible: boolean;
		priority: number;
		is_active: boolean;
		notes?: string;
	}>): Promise<DeductionComponent> {
		return await apiPatch<DeductionComponent>(`/deduction-components/${id}`, component);
	},
	
	async delete(id: number): Promise<void> {
		return await apiDelete<void>(`/deduction-components/${id}`);
	}
};


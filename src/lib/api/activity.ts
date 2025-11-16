import { apiGet } from './client.js';
import type { PaginatedResponse } from './persons.js';

export interface ActivityLog {
	id: number;
	tenant_id: number;
	user_id: number;
	table_name: string;
	record_id: string | number;
	action: 'insert' | 'update' | 'delete';
	before: Record<string, any> | null;
	after: Record<string, any> | null;
	created_at: string;
	updated_at?: string;
	user?: {
		id: number;
		name: string;
		email: string;
	};
	tenant?: {
		id: number;
		name: string;
		code: string;
	};
}

export const activityApi = {
	async list(params?: {
		table_name?: string;
		action?: 'create' | 'update' | 'delete';
		user_id?: number;
		record_id?: string;
		date_from?: string;
		date_to?: string;
		per_page?: number;
		page?: number;
	}): Promise<PaginatedResponse<ActivityLog>> {
		return await apiGet<PaginatedResponse<ActivityLog>>('/logs/activity', params);
	},
	
	async get(id: number): Promise<ActivityLog> {
		return await apiGet<ActivityLog>(`/logs/activity/${id}`);
	}
};


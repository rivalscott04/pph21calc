import { apiPost, apiGet } from './client.js';
import type { PaginatedResponse } from './persons.js';

export interface CoreTaxLog {
	id: number;
	tenant_id: number;
	period_id: number;
	payload_json: any;
	status: 'pending' | 'sent' | 'validated' | 'failed';
	ref_no: string | null;
	response_json: any;
	created_at: string;
	updated_at: string;
	period?: {
		id: number;
		year: number;
		month: number;
	};
	tenant?: {
		id: number;
		name: string;
		code: string;
	};
}

export const coretaxApi = {
	async export(periodId: number): Promise<{
		message: string;
		period: {
			id: number;
			year: number;
			month: number;
		};
		bpa_data: any;
	}> {
		return await apiPost<{
			message: string;
			period: {
				id: number;
				year: number;
				month: number;
			};
			bpa_data: any;
		}>(`/coretax/export`, { period_id: periodId });
	},
	
	async upload(periodId: number): Promise<{
		message: string;
		log_id: number;
		ref_no?: string;
		status: string;
		errors?: string[];
	}> {
		return await apiPost<{
			message: string;
			log_id: number;
			ref_no?: string;
			status: string;
			errors?: string[];
		}>(`/coretax/upload`, { period_id: periodId });
	},
	
	async listLogs(params?: {
		period_id?: number;
		status?: string;
		per_page?: number;
	}): Promise<PaginatedResponse<CoreTaxLog>> {
		return await apiGet<PaginatedResponse<CoreTaxLog>>('/coretax/logs', params);
	},
	
	async getLog(id: number): Promise<CoreTaxLog> {
		return await apiGet<CoreTaxLog>(`/coretax/logs/${id}`);
	}
};


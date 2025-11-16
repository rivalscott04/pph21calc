import { apiGet, apiPost, apiPatch } from './client.js';
import type { PaginatedResponse } from './persons.js';

export interface Period {
	id: number;
	tenant_id: number;
	year: number;
	month: number;
	status: 'draft' | 'reviewed' | 'approved' | 'posted';
	created_at: string;
	updated_at: string;
}

export interface Earning {
	id: number;
	period_id: number;
	employment_id: number;
	component_id: number;
	amount: number;
	meta?: any;
	component?: {
		id: number;
		code: string;
		name: string;
		group: string;
		taxable: boolean;
	};
	employment?: {
		id: number;
		person?: {
			id: string;
			full_name: string;
		};
		orgUnit?: {
			id: number;
			name: string;
		};
	};
	period?: Period;
}

export interface Deduction {
	id: number;
	period_id: number;
	employment_id: number;
	deduction_component_id?: number;
	type?: 'iuran_pensiun' | 'zakat' | 'lainnya'; // Keep for backward compatibility
	amount: number;
	deductionComponent?: {
		id: number;
		code: string;
		name: string;
		type: string;
		calculation_type: string;
		is_tax_deductible: boolean;
	};
	employment?: {
		id: number;
		person?: {
			id: string;
			full_name: string;
		};
		orgUnit?: {
			id: number;
			name: string;
		};
	};
	period?: Period;
}

export interface PayrollCalculation {
	id: number;
	period_id: number;
	employment_id: number;
	bruto: number;
	biaya_jabatan: number;
	neto_masa: number;
	pkp_masa: number;
	pph21_masa: number;
	created_at: string;
	updated_at: string;
}

export interface PayrollSummary {
	period_id: number;
	period: string;
	status: string;
	total_employees: number;
	total_bruto: number;
	total_neto: number;
	total_pph21: number;
	calculations: Array<{
		employment_id: number;
		person_name: string;
		org_unit: string;
		bruto: number;
		neto_masa: number;
		pph21_masa: number;
	}>;
}

export const payrollApi = {
	// Periods
	async listPeriods(params?: {
		year?: number;
		month?: number;
		status?: string;
		per_page?: number;
	}): Promise<PaginatedResponse<Period>> {
		return await apiGet<PaginatedResponse<Period>>('/periods', params);
	},
	
	async createPeriod(period: {
		year: number;
		month: number;
	}): Promise<Period> {
		return await apiPost<Period>('/periods', period);
	},
	
	async updatePeriodStatus(id: number, status: 'draft' | 'reviewed' | 'approved' | 'posted'): Promise<Period> {
		return await apiPatch<Period>(`/periods/${id}/status`, { status });
	},
	
	// Earnings
	async listEarnings(params?: {
		period?: number;
		employment_id?: number;
		per_page?: number;
	}): Promise<PaginatedResponse<Earning>> {
		return await apiGet<PaginatedResponse<Earning>>('/earnings', params);
	},
	
	async storeEarnings(data: {
		period_id: number;
		earnings: Array<{
			employment_id: number;
			component_id: number;
			amount: number;
			meta?: any;
		}>;
	}): Promise<{ message: string; created: number; updated: number }> {
		return await apiPost<{ message: string; created: number; updated: number }>('/earnings', data);
	},
	
	// Deductions
	async listDeductions(params?: {
		period?: number;
		employment_id?: number;
		per_page?: number;
	}): Promise<PaginatedResponse<Deduction>> {
		return await apiGet<PaginatedResponse<Deduction>>('/deductions', params);
	},
	
	async storeDeductions(data: {
		period_id: number;
		deductions: Array<{
			employment_id: number;
			deduction_component_id: number;
			amount: number;
		}>;
	}): Promise<{ message: string; created: number; updated: number }> {
		return await apiPost<{ message: string; created: number; updated: number }>('/deductions', data);
	},
	
	// Payroll Calculations
	async preview(periodId: number): Promise<{
		period_id: number;
		period: string;
		previews: Array<{
			employment_id: number;
			person_name: string;
			bruto: number;
			biaya_jabatan: number;
			iuran_pensiun: number;
			zakat: number;
			neto_masa: number;
			ptkp_yearly: number;
			pkp_annualized: number;
			pph21_masa: number;
			pph21_ytd: number;
			pph21_settlement_dec: number;
		}>;
	}> {
		return await apiPost<{
			period_id: number;
			period: string;
			previews: Array<{
				employment_id: number;
				person_name: string;
				bruto: number;
				biaya_jabatan: number;
				iuran_pensiun: number;
				zakat: number;
				neto_masa: number;
				ptkp_yearly: number;
				pkp_annualized: number;
				pph21_masa: number;
				pph21_ytd: number;
				pph21_settlement_dec: number;
			}>;
		}>(`/payroll/${periodId}/preview`, {});
	},
	
	async commit(periodId: number): Promise<{ message: string }> {
		return await apiPost<{ message: string }>(`/payroll/${periodId}/commit`);
	},
	
	async summary(periodId: number): Promise<PayrollSummary> {
		return await apiGet<PayrollSummary>(`/payroll/${periodId}/summary`);
	},
	
	async slip(periodId: number, employmentId: number): Promise<any> {
		return await apiGet<any>(`/payroll/${periodId}/slip/${employmentId}`);
	}
};


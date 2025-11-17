import { apiPost, apiGet } from './client.js';
import type { PaginatedResponse } from './persons.js';

export interface CalculatorRequest {
	ptkp_code: string;
	bruto: number;
	biaya_jabatan?: number;
	iuran_pensiun?: number;
	zakat?: number;
	month?: number;
	has_npwp?: boolean;
}

export interface CalculatorResponse {
	bruto: number;
	biaya_jabatan: number;
	iuran_pensiun: number;
	zakat: number;
	neto_year: number;
	neto_masa: number; // For backward compatibility (same as neto_year in standalone mode)
	ptkp_yearly: number;
	pkp_year: number;
	pkp_annualized: number; // For backward compatibility (same as pkp_year)
	pph21_year: number;
	pph21_masa: number;
	notes: string[];
}

export interface EmployeeSearchResult {
	id: number;
	person_name: string;
	nik: string;
	org_unit: string;
	employment_type: string;
	ptkp_code: string;
	has_npwp: boolean;
}

export interface BatchCalculationItem {
	employment_id: number;
	bruto: number;
	biaya_jabatan?: number;
	iuran_pensiun?: number;
	zakat?: number; // Added zakat to the interface
}

export interface BatchCalculationRequest {
	calculations: BatchCalculationItem[];
	month?: number;
}

export interface BatchCalculationResult {
	employment_id: number;
	person_name: string;
	ptkp_code: string;
	has_npwp: boolean;
	bruto: number;
	biaya_jabatan: number;
	iuran_pensiun: number;
	zakat: number;
	neto_year?: number; // Added for standalone calculator
	neto_masa: number;
	ptkp_yearly: number;
	pkp_year?: number; // Added for standalone calculator
	pkp_annualized: number;
	pph21_year?: number; // Added for standalone calculator
	pph21_masa: number;
	notes: string[];
	error?: string;
}

export interface BatchCalculationResponse {
	month: number;
	total: number;
	success: number;
	failed: number;
	results: BatchCalculationResult[];
}

export interface CalculationHistoryEarningBreakdown {
	component_id: number;
	monthly_amount: number;
	annual_amount: number;
}

export interface CalculationHistoryDeductionBreakdown {
	deduction_component_id: number;
	monthly_amount: number;
	annual_amount: number;
}

export interface CalculationHistory {
	id: number;
	tenant_id: number;
	user_id: number;
	employment_id: number | null;
	person_name: string;
	ptkp_code: string;
	has_npwp: boolean;
	year: number;
	month: number;
	calculation_mode?: 'monthly' | 'yearly';
	bruto: number;
	biaya_jabatan: number;
	iuran_pensiun: number;
	zakat: number;
	neto_masa: number;
	ptkp_yearly: number;
	pkp_annualized: number;
	pph21_masa: number;
	notes: string[] | null;
	earnings_breakdown?: CalculationHistoryEarningBreakdown[] | null;
	deductions_breakdown?: CalculationHistoryDeductionBreakdown[] | null;
	created_at: string;
	updated_at: string;
}

export interface SaveHistoryRequest {
	calculations: Array<{
		employment_id?: number | null;
		person_name: string;
		ptkp_code: string;
		has_npwp: boolean;
		year: number;
		month: number;
		calculation_mode?: 'monthly' | 'yearly';
		bruto: number;
		biaya_jabatan: number;
		iuran_pensiun: number;
		zakat: number;
		neto_masa: number;
		ptkp_yearly: number;
		pkp_annualized: number;
		pph21_masa: number;
		notes?: string[] | null;
		earnings_breakdown?: CalculationHistoryEarningBreakdown[];
		deductions_breakdown?: CalculationHistoryDeductionBreakdown[];
	}>;
}

export interface HistorySummary {
	year: number;
	count: number;
	total_bruto: number;
	total_pph21: number;
}

export interface EmployeeHistoryListItem {
	employment_id: number;
	person_name: string;
	ptkp_code: string;
	has_npwp: boolean;
	year: number;
	latest_calculation_date: string;
	status_text: string;
	calculation_count: number;
	total_bruto_ytd: number;
	total_neto_ytd: number;
	total_pph21_ytd: number;
	total_pkp_ytd: number;
}

export interface Period {
	month: number;
	year: number;
	calculation_date: string;
	bruto: number;
	biaya_jabatan: number;
	iuran_pensiun: number;
	zakat: number;
	neto_masa: number;
	neto_annualized: number;
	ptkp_yearly: number;
	pkp_annualized: number;
	pph21_masa: number;
	pph21_ytd: number;
	is_reconciliation: boolean;
	notes: string[];
}

export interface EmployeeHistoryDetail {
	employee: {
		employment_id: number;
		name: string;
		nip: string | null;
		ptkp_code: string;
		has_npwp: boolean;
		position: string | null;
		npwp_number: string | null;
	};
	year: number;
	summary: {
		months_with_calculation: number[];
		total_pph21_ytd: number;
		total_pph21_year: number;
		total_pkp_year: number;
	};
	periods: Period[];
}

export const calculatorApi = {
	async calculatePPh21(data: CalculatorRequest): Promise<CalculatorResponse> {
		return await apiPost<CalculatorResponse>('/calculator/pph21', data);
	},

	async calculateBatch(data: BatchCalculationRequest): Promise<BatchCalculationResponse> {
		return await apiPost<BatchCalculationResponse>('/calculator/batch', data);
	},

	async searchEmployees(params?: {
		search?: string;
		org_unit_id?: number;
		per_page?: number;
		page?: number;
	}): Promise<PaginatedResponse<EmployeeSearchResult>> {
		return await apiGet<PaginatedResponse<EmployeeSearchResult>>('/calculator/employees', params);
	},

	async saveHistory(data: SaveHistoryRequest): Promise<{
		message: string;
		saved_count: number;
		history: CalculationHistory[];
	}> {
		return await apiPost('/calculator/history', data);
	},

	async getHistory(params?: {
		year?: number;
		month?: number;
		employment_id?: number;
		per_page?: number;
		page?: number;
	}): Promise<PaginatedResponse<CalculationHistory>> {
		return await apiGet<PaginatedResponse<CalculationHistory>>('/calculator/history', params);
	},

	async getHistorySummary(): Promise<HistorySummary[]> {
		return await apiGet<HistorySummary[]>('/calculator/history/summary');
	},

	async getEmployeeHistoryList(params?: {
		year?: number;
	}): Promise<{ data: EmployeeHistoryListItem[] }> {
		return await apiGet<{ data: EmployeeHistoryListItem[] }>('/calculator/history/employees', params);
	},

	async getEmployeeHistoryDetail(employmentId: number, params?: {
		year?: number;
	}): Promise<EmployeeHistoryDetail> {
		return await apiGet<EmployeeHistoryDetail>(`/calculator/history/${employmentId}`, params);
	}
};


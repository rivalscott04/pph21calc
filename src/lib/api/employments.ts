import { apiGet, apiPost, apiPatch } from './client.js';
import type { PaginatedResponse } from './persons.js';
import type { Person } from './persons.js';
import type { OrgUnit } from './orgUnits.js';

export interface Employment {
	id: number;
	tenant_id: number;
	person_id: string;
	org_unit_id: number;
	employment_type: string;
	start_date: string;
	end_date: string | null;
	primary_payroll: boolean;
	created_at: string;
	updated_at: string;
	person?: Person;
	orgUnit?: OrgUnit;
	payrollSubject?: PayrollSubject;
}

export interface PayrollSubject {
	id: number;
	employment_id: number;
	ptkp_status: string;
	tax_profile: string;
	npwp: string | null;
	created_at: string;
	updated_at: string;
}

export const employmentsApi = {
	async list(params?: {
		person_id?: string;
		org_unit_id?: number;
		employment_type?: string;
		active?: boolean;
		primary_payroll?: boolean;
		per_page?: number;
	}): Promise<PaginatedResponse<Employment>> {
		return await apiGet<PaginatedResponse<Employment>>('/employments', params);
	},
	
	async get(id: number): Promise<Employment> {
		return await apiGet<Employment>(`/employments/${id}`);
	},
	
	async create(employment: {
		person_id: string;
		org_unit_id: number;
		employment_type: string;
		start_date: string;
		end_date?: string | null;
		primary_payroll?: boolean;
	}): Promise<Employment> {
		return await apiPost<Employment>('/employments', employment);
	},
	
	async update(id: number, employment: Partial<{
		person_id: string;
		org_unit_id: number;
		employment_type: string;
		start_date: string;
		end_date: string | null;
		primary_payroll: boolean;
	}>): Promise<Employment> {
		return await apiPatch<Employment>(`/employments/${id}`, employment);
	},
	
	async getPayrollSubjects(employmentId: number): Promise<PayrollSubject[]> {
		return await apiGet<PayrollSubject[]>(`/employments/${employmentId}/payroll-subjects`);
	},
	
	async createPayrollSubject(employmentId: number, subject: {
		ptkp_status: string;
		tax_profile: string;
		npwp?: string | null;
	}): Promise<PayrollSubject> {
		return await apiPost<PayrollSubject>(`/employments/${employmentId}/payroll-subjects`, subject);
	},
	
	async updatePayrollSubject(employmentId: number, subjectId: number, subject: Partial<{
		ptkp_status: string;
		tax_profile: string;
		npwp: string | null;
	}>): Promise<PayrollSubject> {
		return await apiPatch<PayrollSubject>(`/employments/${employmentId}/payroll-subjects/${subjectId}`, subject);
	}
};


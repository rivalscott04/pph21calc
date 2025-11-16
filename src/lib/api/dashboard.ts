import { apiGet } from './client.js';

export interface DashboardSummary {
	total_employees: number;
	total_persons: number;
	total_periods: number;
	current_year_periods: number;
	total_pph21_ytd: number;
	total_pph21_this_month: number;
	recent_activity_count: number;
	pending_periods: number;
}

export interface ChartData {
	type: string;
	data: any[];
}

export const dashboardApi = {
	async getSummary(tenantId?: number): Promise<DashboardSummary> {
		return await apiGet<DashboardSummary>('/dashboard/summary', tenantId ? { tenant_id: tenantId } : undefined);
	},
	
	async getChart(type: 'pph21_monthly' | 'employees_by_org' | 'activity_timeline', tenantId?: number): Promise<ChartData> {
		return await apiGet<ChartData>('/dashboard/chart', {
			type,
			...(tenantId ? { tenant_id: tenantId } : {})
		});
	}
};


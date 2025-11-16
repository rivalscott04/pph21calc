import { apiGet, apiPatch, apiPost, apiRequest } from './client.js';

export interface ConfigModule {
	id: number;
	tenant_id: number;
	core_payroll: boolean;
	coretax_integration: boolean;
	compliance_ojk: boolean;
	compliance_pdp: boolean;
	audit_trail: boolean;
	bpjs_integration: boolean;
	syariah_extension: boolean;
	created_at?: string;
	updated_at?: string;
}

export interface ConfigBranding {
	id: number;
	tenant_id: number;
	primary: string;
	secondary: string;
	accent: string;
	neutral: string;
	base100: string;
	button: string;
	link_hover: string;
	badge_success: string;
	badge_error: string;
	badge_primary: string;
	badge_secondary: string;
	badge_accent: string;
	toast_success: string;
	toast_error: string;
	created_at?: string;
	updated_at?: string;
}

export interface IdentifierScheme {
	id: number;
	code: string;
	label: string;
	prefix: string | null; // Prefix untuk ID (contoh: "NTB")
	entity_type: string | null;
	regex_pattern: string | null;
	length_min: number | null;
	length_max: number | null;
	normalize_rule: string | null;
	example: string | null;
	checksum_type: string | null;
	tenant_id: number | null;
}

export const configApi = {
	// Modules
	async getModules(tenantId?: number): Promise<ConfigModule> {
		return await apiGet<ConfigModule>('/config/modules', tenantId ? { tenant_id: tenantId } : undefined);
	},
	
	async updateModules(modules: Partial<ConfigModule>, tenantId?: number): Promise<ConfigModule> {
		return await apiPatch<ConfigModule>('/config/modules', { ...modules, tenant_id: tenantId });
	},
	
	// Branding
	async getBranding(tenantId?: number): Promise<ConfigBranding | null> {
		return await apiGet<ConfigBranding | null>('/config/branding', tenantId ? { tenant_id: tenantId } : undefined);
	},
	
	async updateBranding(branding: Partial<ConfigBranding>, tenantId?: number): Promise<ConfigBranding> {
		return await apiPatch<ConfigBranding>('/config/branding', { ...branding, tenant_id: tenantId });
	},
	
	// Identifier Schemes
	async getIdentifierSchemes(entityType?: string, tenantId?: number): Promise<IdentifierScheme[]> {
		const params: Record<string, any> = {};
		if (entityType) params.entity = entityType;
		if (tenantId) params.tenant_id = tenantId;
		return await apiGet<IdentifierScheme[]>('/config/identifier-schemes', Object.keys(params).length > 0 ? params : undefined);
	},
	
	async createIdentifierScheme(scheme: any, tenantId?: number): Promise<IdentifierScheme> {
		return await apiPost<IdentifierScheme>('/config/identifier-schemes', { ...scheme, tenant_id: tenantId });
	},
	
	async updateIdentifierScheme(id: number, scheme: Partial<IdentifierScheme>, tenantId?: number): Promise<IdentifierScheme> {
		return await apiPatch<IdentifierScheme>(`/config/identifier-schemes/${id}`, { ...scheme, tenant_id: tenantId });
	},
	
	async deleteIdentifierScheme(id: number, tenantId?: number): Promise<void> {
		const endpoint = `/config/identifier-schemes/${id}${tenantId ? `?tenant_id=${tenantId}` : ''}`;
		return await apiRequest<void>(endpoint, { method: 'DELETE' });
	}
};








import { writable, derived, get } from 'svelte/store';
import { configApi } from '../api/config.js';
import { auth } from './auth.js';
import { toast } from './toast.js';

export interface ModuleConfig {
	id?: number;
	tenant_id?: number;
	core_payroll: boolean;
	coretax_integration: boolean;
	compliance_ojk: boolean;
	compliance_pdp: boolean;
	audit_trail: boolean;
	bpjs_integration: boolean;
	syariah_extension: boolean;
}

// Store untuk module config
const createModulesStore = () => {
	const { subscribe, set, update } = writable<ModuleConfig | null>(null);
	
	return {
		subscribe,
		
		// Load modules from API
		async load(tenantId?: number): Promise<void> {
			try {
				const user = get(auth);
				const targetTenantId = tenantId || user?.tenant?.id;
				
				if (!targetTenantId) {
					console.warn('No tenant ID available for loading modules');
					return;
				}
				
				// Backend returns ConfigModule object directly with boolean fields
				const response = await configApi.getModules(targetTenantId);
				
				// Backend returns ConfigModule object directly
				if (response && typeof response === 'object') {
					set({
						id: response.id,
						tenant_id: response.tenant_id,
						core_payroll: response.core_payroll ?? true,
						coretax_integration: response.coretax_integration ?? false,
						compliance_ojk: response.compliance_ojk ?? false,
						compliance_pdp: response.compliance_pdp ?? false,
						audit_trail: response.audit_trail ?? true,
						bpjs_integration: response.bpjs_integration ?? false,
						syariah_extension: response.syariah_extension ?? false
					});
				} else {
					// Fallback: create default config
					set({
						core_payroll: true,
						coretax_integration: false,
						compliance_ojk: false,
						compliance_pdp: false,
						audit_trail: true,
						bpjs_integration: false,
						syariah_extension: false
					});
				}
			} catch (error) {
				console.error('Failed to load modules:', error);
				toast.error('Gagal memuat konfigurasi modul');
				
				// Set default config on error
				set({
					core_payroll: true,
					coretax_integration: false,
					compliance_ojk: false,
					compliance_pdp: false,
					audit_trail: true,
					bpjs_integration: false,
					syariah_extension: false
				});
			}
		},
		
		// Update modules
		async update(updates: Partial<ModuleConfig>, tenantId?: number): Promise<void> {
			try {
				const user = get(auth);
				const targetTenantId = tenantId || user?.tenant?.id;
				
				if (!targetTenantId) {
					throw new Error('No tenant ID available');
				}
				
				// Backend expects object with boolean fields
				const response = await configApi.updateModules(updates, targetTenantId);
				
				// Update store with response
				if (response && typeof response === 'object') {
					update(() => ({
						id: response.id,
						tenant_id: response.tenant_id,
						core_payroll: response.core_payroll ?? true,
						coretax_integration: response.coretax_integration ?? false,
						compliance_ojk: response.compliance_ojk ?? false,
						compliance_pdp: response.compliance_pdp ?? false,
						audit_trail: response.audit_trail ?? true,
						bpjs_integration: response.bpjs_integration ?? false,
						syariah_extension: response.syariah_extension ?? false
					}));
					toast.success('Konfigurasi modul berhasil diperbarui');
				} else {
					// Update with provided values
					update((current) => ({
						...(current || {}),
						...updates
					} as ModuleConfig));
					toast.success('Konfigurasi modul berhasil diperbarui');
				}
			} catch (error) {
				console.error('Failed to update modules:', error);
				toast.error('Gagal memperbarui konfigurasi modul');
				throw error;
			}
		},
		
		// Reset store
		reset(): void {
			set(null);
		}
	};
};

export const modules = createModulesStore();

// Derived stores for individual module checks
export const isCorePayrollEnabled = derived(modules, ($modules) => $modules?.core_payroll ?? true);
export const isCoreTaxEnabled = derived(modules, ($modules) => $modules?.coretax_integration ?? false);
export const isComplianceOjkEnabled = derived(modules, ($modules) => $modules?.compliance_ojk ?? false);
export const isCompliancePdpEnabled = derived(modules, ($modules) => $modules?.compliance_pdp ?? false);
export const isAuditTrailEnabled = derived(modules, ($modules) => $modules?.audit_trail ?? true);
export const isBpjsEnabled = derived(modules, ($modules) => $modules?.bpjs_integration ?? false);
export const isSyariahEnabled = derived(modules, ($modules) => $modules?.syariah_extension ?? false);

// Helper function to check if a module is enabled
export function isModuleEnabled(moduleName: keyof ModuleConfig): boolean {
	const $modules = get(modules);
	if (!$modules) return false;
	return $modules[moduleName] === true;
}

// Initialize modules on app load (will be called from layout) - CLIENT-SIDE ONLY
export async function initModules(): Promise<void> {
	// Only run in browser (client-side)
	if (typeof window === 'undefined') return;
	
	try {
		const user = get(auth);
		if (user?.tenant?.id) {
			await modules.load();
		}
	} catch (error) {
		// Store might not be ready yet or auth not loaded, that's okay
		console.warn('Failed to initialize modules:', error);
	}
}


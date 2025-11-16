import { writable, get } from 'svelte/store';
import { configApi, type ConfigBranding } from '../api/config.js';
import { auth } from './auth.js';
import { toast } from './toast.js';

export interface BrandColors {
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
}

// Store untuk brand colors (default values - dark theme)
export const brandColors = writable<BrandColors>({
	primary: '#0ea5e9',
	secondary: '#10b981',
	accent: '#f59e0b',
	neutral: '#3d4451',
	base100: '#1e293b', // dark slate-800
	button: '#0ea5e9',
	link_hover: '#0ea5e9',
	badge_success: '#10b981',
	badge_error: '#ef4444',
	badge_primary: '#0ea5e9',
	badge_secondary: '#10b981',
	badge_accent: '#f59e0b',
	toast_success: '#10b981',
	toast_error: '#ef4444'
});

// Convert HEX to HSL
function hexToHsl(hex: string): string {
	// Remove # if present
	hex = hex.replace('#', '');
	
	// Parse RGB
	const r = parseInt(hex.substring(0, 2), 16) / 255;
	const g = parseInt(hex.substring(2, 4), 16) / 255;
	const b = parseInt(hex.substring(4, 6), 16) / 255;

	const max = Math.max(r, g, b);
	const min = Math.min(r, g, b);
	let h = 0;
	let s = 0;
	const l = (max + min) / 2;

	if (max !== min) {
		const d = max - min;
		s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
		
		switch (max) {
			case r:
				h = ((g - b) / d + (g < b ? 6 : 0)) / 6;
				break;
			case g:
				h = ((b - r) / d + 2) / 6;
				break;
			case b:
				h = ((r - g) / d + 4) / 6;
				break;
		}
	}

	return `${Math.round(h * 360)} ${Math.round(s * 100)}% ${Math.round(l * 100)}%`;
}

// Apply brand theme to CSS variables
export function applyBrandTheme(colors: BrandColors) {
	if (typeof document === 'undefined') return;
	
	try {
		const root = document.documentElement;
		
		// Set data-theme to "brand"
		root.setAttribute('data-theme', 'brand');
		
		// Convert HEX to HSL and set CSS variables
		root.style.setProperty('--p', hexToHsl(colors.primary));
		root.style.setProperty('--s', hexToHsl(colors.secondary));
		root.style.setProperty('--a', hexToHsl(colors.accent));
		root.style.setProperty('--n', hexToHsl(colors.neutral));
		root.style.setProperty('--b1', hexToHsl(colors.base100));
		// Button color - use custom CSS variable for button component
		root.style.setProperty('--btn-color', colors.button);
		// Link hover color - use custom CSS variable for link hover
		root.style.setProperty('--link-hover-color', colors.link_hover);
		// Badge colors - use custom CSS variables for badge components
		root.style.setProperty('--badge-success-color', colors.badge_success);
		root.style.setProperty('--badge-error-color', colors.badge_error);
		root.style.setProperty('--badge-primary-color', colors.badge_primary);
		root.style.setProperty('--badge-secondary-color', colors.badge_secondary);
		root.style.setProperty('--badge-accent-color', colors.badge_accent);
		// Toast colors - use custom CSS variables for toast components
		root.style.setProperty('--toast-success-color', colors.toast_success);
		root.style.setProperty('--toast-error-color', colors.toast_error);
		
		// Update store (use setTimeout to avoid blocking)
		setTimeout(() => {
			brandColors.set(colors);
		}, 0);
	} catch (error) {
		console.error('Error applying brand theme:', error);
	}
}

// Load brand colors from localStorage - REMOVED: Always load from API based on tenant context

// Convert ConfigBranding to BrandColors
function configToBrandColors(config: ConfigBranding): BrandColors {
	return {
		primary: config.primary,
		secondary: config.secondary,
		accent: config.accent,
		neutral: config.neutral,
		base100: config.base100,
		button: config.button || config.primary, // fallback to primary if button not set
		link_hover: config.link_hover || config.primary, // fallback to primary if link_hover not set
		badge_success: config.badge_success || config.secondary, // fallback to secondary if badge_success not set
		badge_error: config.badge_error || '#ef4444', // fallback to red if badge_error not set
		badge_primary: config.badge_primary || config.primary, // fallback to primary if badge_primary not set
		badge_secondary: config.badge_secondary || config.secondary, // fallback to secondary if badge_secondary not set
		badge_accent: config.badge_accent || config.accent, // fallback to accent if badge_accent not set
		toast_success: config.toast_success || config.secondary, // fallback to secondary if toast_success not set
		toast_error: config.toast_error || '#ef4444' // fallback to red if toast_error not set
	};
}

// Load brand colors from API
export async function loadBrandColorsFromAPI(tenantId?: number): Promise<BrandColors | null> {
	try {
		const user = get(auth);
		const targetTenantId = tenantId || user?.tenant?.id;
		
		if (!targetTenantId) {
			return null;
		}
		
		const config = await configApi.getBranding(targetTenantId);
		if (config) {
			return configToBrandColors(config);
		}
		return null;
	} catch (error) {
		console.error('Failed to load brand colors from API:', error);
		return null;
	}
}

// Update brand colors via API
export async function updateBrandColorsViaAPI(colors: BrandColors, tenantId?: number): Promise<void> {
	try {
		const user = get(auth);
		const targetTenantId = tenantId || user?.tenant?.id;
		
		if (!targetTenantId) {
			throw new Error('No tenant ID available');
		}
		
		const config = await configApi.updateBranding(colors, targetTenantId);
		if (config) {
			const brandColors = configToBrandColors(config);
			applyBrandTheme(brandColors);
			toast.success('Branding berhasil diperbarui');
		}
	} catch (error) {
		console.error('Failed to update brand colors via API:', error);
		toast.error('Gagal memperbarui branding');
		throw error;
	}
}

// Initialize brand theme on app load (CLIENT-SIDE ONLY)
// Always loads from API based on tenant context - NO localStorage
export async function initBrandTheme(tenantId?: number) {
	// Only run in browser (client-side)
	if (typeof window === 'undefined' || typeof document === 'undefined') return;
	
	try {
		// Get tenant ID from parameter or from auth store
		let targetTenantId = tenantId;
		
		if (!targetTenantId) {
			try {
				const user = get(auth);
				targetTenantId = user?.tenant?.id;
			} catch (e) {
				// Store might not be ready yet, that's okay
			}
		}
		
		// If we have tenant ID, load from API
		if (targetTenantId) {
			try {
				const apiColors = await loadBrandColorsFromAPI(targetTenantId);
				if (apiColors) {
					applyBrandTheme(apiColors);
					return;
				}
			} catch (error) {
				console.warn('Failed to load brand colors from API:', error);
			}
		}
		
		// If no tenant ID or API failed, use defaults
		// Use default colors (dark theme) - don't update store to avoid re-render
		const defaultColors: BrandColors = {
			primary: '#0ea5e9',
			secondary: '#10b981',
			accent: '#f59e0b',
			neutral: '#3d4451',
			base100: '#1e293b',
			button: '#0ea5e9',
			link_hover: '#0ea5e9',
			badge_success: '#10b981',
			badge_error: '#ef4444',
			badge_primary: '#0ea5e9',
			badge_secondary: '#10b981',
			badge_accent: '#f59e0b',
			toast_success: '#10b981',
			toast_error: '#ef4444'
		};
		// Apply directly without updating store to prevent infinite loop
		const root = document.documentElement;
		root.setAttribute('data-theme', 'brand');
		root.style.setProperty('--p', hexToHsl(defaultColors.primary));
		root.style.setProperty('--s', hexToHsl(defaultColors.secondary));
		root.style.setProperty('--a', hexToHsl(defaultColors.accent));
		root.style.setProperty('--n', hexToHsl(defaultColors.neutral));
		root.style.setProperty('--b1', hexToHsl(defaultColors.base100));
		root.style.setProperty('--btn-color', defaultColors.button);
		root.style.setProperty('--link-hover-color', defaultColors.link_hover);
		root.style.setProperty('--badge-success-color', defaultColors.badge_success);
		root.style.setProperty('--badge-error-color', defaultColors.badge_error);
		root.style.setProperty('--badge-primary-color', defaultColors.badge_primary);
		root.style.setProperty('--badge-secondary-color', defaultColors.badge_secondary);
		root.style.setProperty('--badge-accent-color', defaultColors.badge_accent);
		root.style.setProperty('--toast-success-color', defaultColors.toast_success);
		root.style.setProperty('--toast-error-color', defaultColors.toast_error);
	} catch (error) {
		console.error('Error initializing brand theme:', error);
		// Apply default colors even on error to prevent white screen
		if (typeof document !== 'undefined') {
			const root = document.documentElement;
			root.setAttribute('data-theme', 'brand');
			const defaultColors: BrandColors = {
				primary: '#0ea5e9',
				secondary: '#10b981',
				accent: '#f59e0b',
				neutral: '#3d4451',
				base100: '#1e293b',
				button: '#0ea5e9',
				link_hover: '#0ea5e9',
				badge_success: '#10b981',
				badge_error: '#ef4444',
				badge_primary: '#0ea5e9',
				badge_secondary: '#10b981',
				badge_accent: '#f59e0b',
				toast_success: '#10b981',
				toast_error: '#ef4444'
			};
			root.style.setProperty('--p', hexToHsl(defaultColors.primary));
			root.style.setProperty('--s', hexToHsl(defaultColors.secondary));
			root.style.setProperty('--a', hexToHsl(defaultColors.accent));
			root.style.setProperty('--n', hexToHsl(defaultColors.neutral));
			root.style.setProperty('--b1', hexToHsl(defaultColors.base100));
			root.style.setProperty('--btn-color', defaultColors.button);
			root.style.setProperty('--link-hover-color', defaultColors.link_hover);
			root.style.setProperty('--badge-success-color', defaultColors.badge_success);
			root.style.setProperty('--badge-error-color', defaultColors.badge_error);
			root.style.setProperty('--badge-primary-color', defaultColors.badge_primary);
			root.style.setProperty('--badge-secondary-color', defaultColors.badge_secondary);
			root.style.setProperty('--badge-accent-color', defaultColors.badge_accent);
			root.style.setProperty('--toast-success-color', defaultColors.toast_success);
			root.style.setProperty('--toast-error-color', defaultColors.toast_error);
		}
	}
}

// Watch auth store for tenant changes and reload brand theme
// This ensures brand colors are always correct for the current tenant
export function watchTenantAndReloadTheme() {
	if (typeof window === 'undefined') return;
	
	let lastTenantId: number | undefined = undefined;
	
	auth.subscribe((state) => {
		const currentTenantId = state.user?.tenant?.id;
		
		// If tenant changed, reload brand theme
		if (currentTenantId !== lastTenantId) {
			lastTenantId = currentTenantId;
			if (currentTenantId) {
				initBrandTheme(currentTenantId).catch((error) => {
					console.error('Failed to reload brand theme on tenant change:', error);
				});
			}
		}
	});
}


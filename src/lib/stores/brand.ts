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
	badge: string;
}

// Store untuk brand colors (default values - dark theme)
export const brandColors = writable<BrandColors>({
	primary: '#0ea5e9',
	secondary: '#10b981',
	accent: '#f59e0b',
	neutral: '#3d4451',
	base100: '#1e293b', // dark slate-800
	button: '#0ea5e9',
	badge: '#3d4451'
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
		// Badge color - use custom CSS variable for badge component
		root.style.setProperty('--badge-color', colors.badge);
		
		// Update store (use setTimeout to avoid blocking)
		setTimeout(() => {
			brandColors.set(colors);
		}, 0);
		
		// Save to localStorage
		if (typeof window !== 'undefined') {
			try {
				localStorage.setItem('brandColors', JSON.stringify(colors));
			} catch (e) {
				console.error('Failed to save brand colors to localStorage', e);
			}
		}
	} catch (error) {
		console.error('Error applying brand theme:', error);
	}
}

// Load brand colors from localStorage
export function loadBrandColorsFromStorage(): BrandColors | null {
	if (typeof window === 'undefined') return null;
	
	const stored = localStorage.getItem('brandColors');
	if (stored) {
		try {
			return JSON.parse(stored);
		} catch (e) {
			console.error('Failed to parse brand colors from localStorage', e);
			return null;
		}
	}
	return null;
}

// Convert ConfigBranding to BrandColors
function configToBrandColors(config: ConfigBranding): BrandColors {
	return {
		primary: config.primary,
		secondary: config.secondary,
		accent: config.accent,
		neutral: config.neutral,
		base100: config.base100,
		button: config.button || config.primary, // fallback to primary if button not set
		badge: config.badge || config.neutral // fallback to neutral if badge not set
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
export async function initBrandTheme() {
	// Only run in browser (client-side)
	if (typeof window === 'undefined' || typeof document === 'undefined') return;
	
	try {
		// Try to load from API first (if authenticated)
		// Use try-catch for get() in case store is not initialized
		let user = null;
		try {
			user = get(auth);
		} catch (e) {
			// Store might not be ready yet, that's okay
		}
		
		if (user?.tenant?.id) {
			try {
				const apiColors = await loadBrandColorsFromAPI();
				if (apiColors) {
					applyBrandTheme(apiColors);
					return;
				}
			} catch (error) {
				// API call failed, fall through to localStorage/default
				console.warn('Failed to load brand colors from API, using fallback:', error);
			}
		}
		
		// Fallback to localStorage
		const stored = loadBrandColorsFromStorage();
		if (stored) {
			applyBrandTheme(stored);
		} else {
			// Use default colors (dark theme) - don't update store to avoid re-render
			const defaultColors: BrandColors = {
				primary: '#0ea5e9',
				secondary: '#10b981',
				accent: '#f59e0b',
				neutral: '#3d4451',
				base100: '#1e293b',
				button: '#0ea5e9',
				badge: '#3d4451'
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
			root.style.setProperty('--badge-color', defaultColors.badge);
		}
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
				badge: '#3d4451'
			};
			root.style.setProperty('--p', hexToHsl(defaultColors.primary));
			root.style.setProperty('--s', hexToHsl(defaultColors.secondary));
			root.style.setProperty('--a', hexToHsl(defaultColors.accent));
			root.style.setProperty('--n', hexToHsl(defaultColors.neutral));
			root.style.setProperty('--b1', hexToHsl(defaultColors.base100));
			root.style.setProperty('--btn-color', defaultColors.button);
			root.style.setProperty('--badge-color', defaultColors.badge);
		}
	}
}


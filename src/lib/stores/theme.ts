import { writable } from 'svelte/store';

export type Theme = 'light' | 'dark' | 'brand';

// Store untuk theme (light/dark/brand)
export const theme = writable<Theme>('brand');

// Apply theme to document
export function applyTheme(themeName: Theme) {
	if (typeof document === 'undefined') return;
	
	const root = document.documentElement;
	root.setAttribute('data-theme', themeName);
	
	// Update store
	theme.set(themeName);
	
	// Save to localStorage
	localStorage.setItem('theme', themeName);
}

// Load theme from localStorage
export function loadThemeFromStorage(): Theme {
	if (typeof window === 'undefined') return 'brand';
	
	const stored = localStorage.getItem('theme');
	if (stored === 'light' || stored === 'dark' || stored === 'brand') {
		return stored;
	}
	return 'brand';
}

// Initialize theme on app load
export function initTheme() {
	const stored = loadThemeFromStorage();
	applyTheme(stored);
}


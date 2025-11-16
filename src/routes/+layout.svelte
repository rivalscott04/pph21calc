<script lang="ts">
	import '../app.css';
	import { onMount } from 'svelte';
	import { initBrandTheme } from '$lib/stores/brand.js';
	import { auth } from '$lib/stores/auth.js';

	// Initialize theme immediately (before mount) to prevent flash
	if (typeof document !== 'undefined') {
		// Set default to brand theme immediately
		const root = document.documentElement;
		if (!root.hasAttribute('data-theme')) {
			root.setAttribute('data-theme', 'brand');
		}
	}

	onMount(async () => {
		// Restore session from localStorage (if available)
		try {
			auth.loadFromStorage();
		} catch (error) {
			console.warn('Failed to restore auth from storage:', error);
		}

		// Initialize brand theme - use setTimeout to avoid blocking
		setTimeout(async () => {
			try {
				await initBrandTheme();
			} catch (error) {
				console.error('Theme initialization error:', error);
			}
		}, 0);
		
		// Initialize modules after auth is ready
		setTimeout(async () => {
			try {
				const { initModules } = await import('$lib/stores/modules.js');
				await initModules();
			} catch (error) {
				console.error('Modules initialization error:', error);
			}
		}, 100);
	});
</script>

<slot/>

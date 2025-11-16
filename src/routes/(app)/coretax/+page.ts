import { isCoreTaxEnabled } from '$lib/stores/modules.js';
import { redirect } from '@sveltejs/kit';
import { get } from 'svelte/store';

export async function load() {
	// Guard route: check if CoreTax module is enabled
	const enabled = get(isCoreTaxEnabled);
	
	if (!enabled) {
		throw redirect(302, '/dashboard');
	}
	
	return {};
}


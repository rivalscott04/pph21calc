import { redirect } from '@sveltejs/kit';
import type { LayoutServerLoad } from './$types';

export const load: LayoutServerLoad = async ({ cookies, url }) => {
	// Check if user has auth token in cookies (if using cookie-based auth)
	// For token-based auth, we'll verify in client-side
	
	// If accessing login page, allow it
	if (url.pathname.startsWith('/login')) {
		return {};
	}
	
	// For protected routes, we'll verify token in client-side layout
	// Server-side can't access localStorage, so we do verification in client
	return {};
};


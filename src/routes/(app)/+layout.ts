import type { LayoutLoad } from './$types';

export const load: LayoutLoad = async ({ url }) => {
	// If accessing login page, allow it
	if (url.pathname.startsWith('/login')) {
		return {};
	}

	// For protected routes, we'll verify token in client-side layout
	// Server-side can't access localStorage, so we do verification in client
	return {};
};



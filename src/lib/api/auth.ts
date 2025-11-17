import { apiPost, apiGet, API_BASE_URL } from './client.js';
import { auth, type User, type Tenant } from '$lib/stores/auth.js';
import { toast } from '$lib/stores/toast.js';

export interface LoginRequest {
	email: string;
	password: string;
}

export interface LoginResponse {
	user: User;
	tenant: Tenant | null;
	token: string;
}

export const authApi = {
	async login(email: string, password: string): Promise<LoginResponse> {
		await ensureCsrfCookie();
		const xsrfToken = getXsrfToken();
		
		const response = await apiPost<LoginResponse>(
			'/auth/login',
			{ email, password },
			{
				credentials: 'include',
				headers: xsrfToken
					? {
							'X-XSRF-TOKEN': xsrfToken
					  }
					: undefined
			}
		);
		
		auth.setAuth(response.user, response.tenant, response.token);
		toast.success('Login successful');
		return response;
	},
	
	async logout(): Promise<void> {
		try {
			await apiPost('/auth/logout');
		} catch (error) {
			// Even if logout fails, clear local auth
			console.error('Logout error:', error);
		} finally {
			auth.clearAuth();
			toast.success('Logged out successfully');
		}
	},
	
	async me(): Promise<{ user: User; tenant: Tenant | null }> {
		return await apiGet<{ user: User; tenant: Tenant | null }>('/auth/me');
	}
};

let csrfFetched = false;

function getApiOrigin(): string | null {
	try {
		if (API_BASE_URL.startsWith('http')) {
			const url = new URL(API_BASE_URL);
			return url.origin;
		}
		
		if (typeof window !== 'undefined') {
			const url = new URL(API_BASE_URL, window.location.origin);
			return url.origin;
		}
	} catch (error) {
		console.error('Failed to parse API_BASE_URL', error);
	}
	
	return typeof window !== 'undefined' ? window.location.origin : null;
}

async function ensureCsrfCookie() {
	if (csrfFetched) return;
	
	const origin = getApiOrigin();
	if (!origin) return;
	
	try {
		await fetch(`${origin}/sanctum/csrf-cookie`, {
			credentials: 'include',
			headers: {
				Accept: 'application/json'
			}
		});
		csrfFetched = true;
	} catch (error) {
		console.error('Failed to fetch CSRF cookie', error);
	}
}

function getXsrfToken(): string | undefined {
	if (typeof document === 'undefined') {
		return undefined;
	}
	
	const match = document.cookie
		.split('; ')
		.find((row) => row.startsWith('XSRF-TOKEN='));
	
	if (!match) {
		return undefined;
	}
	
	const value = match.split('=')[1];
	return value ? decodeURIComponent(value) : undefined;
}



import { apiPost, apiGet } from './client.js';
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
		const response = await apiPost<LoginResponse>('/auth/login', { email, password });
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


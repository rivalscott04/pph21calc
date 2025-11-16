import { writable } from 'svelte/store';

export interface User {
	id: number;
	name: string;
	email: string;
	is_superadmin: boolean;
	status?: 'active' | 'inactive';
}

export interface Tenant {
	id: number;
	code: string;
	name: string;
	role: string;
}

export interface AuthState {
	user: User | null;
	tenant: Tenant | null;
	token: string | null;
	isAuthenticated: boolean;
}

const createAuthStore = () => {
	const { subscribe, set, update } = writable<AuthState>({
		user: null,
		tenant: null,
		token: null,
		isAuthenticated: false
	});

	return {
		subscribe,
		setAuth: (user: User, tenant: Tenant | null, token: string) => {
			if (typeof window !== 'undefined') {
				localStorage.setItem('auth_token', token);
				localStorage.setItem('auth_user', JSON.stringify(user));
				if (tenant) {
					localStorage.setItem('auth_tenant', JSON.stringify(tenant));
				}
			}
			set({
				user,
				tenant,
				token,
				isAuthenticated: true
			});
		},
		clearAuth: () => {
			if (typeof window !== 'undefined') {
				localStorage.removeItem('auth_token');
				localStorage.removeItem('auth_user');
				localStorage.removeItem('auth_tenant');
			}
			set({
				user: null,
				tenant: null,
				token: null,
				isAuthenticated: false
			});
		},
		loadFromStorage: () => {
			if (typeof window === 'undefined') return false;
			
			const token = localStorage.getItem('auth_token');
			const userStr = localStorage.getItem('auth_user');
			const tenantStr = localStorage.getItem('auth_tenant');
			
			if (token && userStr) {
				try {
					const user = JSON.parse(userStr);
					const tenant = tenantStr ? JSON.parse(tenantStr) : null;
					// Set auth state but mark as unverified (will be verified by /auth/me call)
					set({
						user,
						tenant,
						token,
						isAuthenticated: false // Will be set to true after verification
					});
					return true;
				} catch (e) {
					console.error('Failed to load auth from storage', e);
					// Clear corrupted data
					localStorage.removeItem('auth_token');
					localStorage.removeItem('auth_user');
					localStorage.removeItem('auth_tenant');
					return false;
				}
			}
			return false;
		},
		getToken: (): string | null => {
			let token: string | null = null;
			subscribe((state) => {
				token = state.token;
			})();
			return token;
		}
	};
};

export const auth = createAuthStore();


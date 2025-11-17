import { auth } from '$lib/stores/auth.js';
import { toast } from '$lib/stores/toast.js';
import { goto } from '$app/navigation';

// Support relative URLs for production (same domain) and absolute URLs for development
// If VITE_API_URL is empty or starts with '/', use relative URL
// Otherwise, use the provided absolute URL
const getApiBaseUrl = (): string => {
	const envUrl = import.meta.env.VITE_API_URL;
	
	// If not set, default to development URL
	if (!envUrl) {
		return 'http://localhost:8000/api';
	}
	
	// If empty string or starts with '/', use relative URL
	if (envUrl === '' || envUrl.startsWith('/')) {
		return envUrl || '/api';
	}
	
	// Otherwise use the provided absolute URL
	return envUrl;
};

export const API_BASE_URL = getApiBaseUrl();

export interface ApiError {
	message: string;
	errors?: Record<string, string[]>;
	error?: string;
}

export class ApiClientError extends Error {
	status: number;
	errors?: Record<string, string[]>;
	
	constructor(message: string, status: number, errors?: Record<string, string[]>) {
		super(message);
		this.name = 'ApiClientError';
		this.status = status;
		this.errors = errors;
	}
}

async function handleResponse<T>(response: Response): Promise<T> {
	const contentType = response.headers.get('content-type');
	const isJson = contentType?.includes('application/json');
	
	if (!response.ok) {
		let errorMessage = `HTTP ${response.status}: ${response.statusText}`;
		let errors: Record<string, string[]> | undefined;
		
		if (isJson) {
			try {
				const data = await response.json();
				errorMessage = data.message || data.error || errorMessage;
				errors = data.errors;
			} catch (e) {
				// Ignore JSON parse errors
			}
		}
		
		// Handle 401 Unauthorized - token expired or invalid
		if (response.status === 401) {
			auth.clearAuth();
			// Only show toast and redirect if we're not already on login page
			if (typeof window !== 'undefined' && !window.location.pathname.startsWith('/login')) {
				toast.error('Session expired. Please login again.');
				goto('/login');
			}
			throw new ApiClientError('Unauthorized', 401);
		}
		
		// Handle 403 Forbidden
		if (response.status === 403) {
			toast.error('You do not have permission to perform this action.');
			throw new ApiClientError(errorMessage, 403, errors);
		}
		
		// Handle 422 Validation Error
		if (response.status === 422) {
			const errorMessages = errors 
				? Object.entries(errors).map(([key, values]) => `${key}: ${values.join(', ')}`).join('\n')
				: errorMessage;
			toast.error(errorMessages);
			throw new ApiClientError(errorMessage, 422, errors);
		}
		
		// Handle 500 Server Error
		if (response.status >= 500) {
			toast.error('Server error. Please try again later.');
			throw new ApiClientError(errorMessage, response.status, errors);
		}
		
		// Other errors
		toast.error(errorMessage);
		throw new ApiClientError(errorMessage, response.status, errors);
	}
	
	if (isJson) {
		return await response.json();
	}
	
	return await response.text() as unknown as T;
}

export async function apiRequest<T>(
	endpoint: string,
	options: RequestInit = {}
): Promise<T> {
	const token = auth.getToken();
	
	const headers: Record<string, string> = {
		'Content-Type': 'application/json',
		'Accept': 'application/json',
		...(options.headers as Record<string, string> || {})
	};
	
	if (token) {
		headers['Authorization'] = `Bearer ${token}`;
	}
	
	// Build URL: if endpoint is already absolute, use it; otherwise combine with base URL
	let url: string;
	if (endpoint.startsWith('http')) {
		url = endpoint;
	} else if (API_BASE_URL.startsWith('/')) {
		// Relative URL: ensure endpoint starts with '/' and combine properly
		const cleanEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;
		url = `${API_BASE_URL}${cleanEndpoint}`;
	} else {
		// Absolute URL: ensure proper joining
		const cleanEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;
		url = `${API_BASE_URL}${cleanEndpoint}`;
	}
	
	try {
		const response = await fetch(url, {
			...options,
			headers
		});
		
		return await handleResponse<T>(response);
	} catch (error) {
		if (error instanceof ApiClientError) {
			throw error;
		}
		
		// Network error
		toast.error('Network error. Please check your connection.');
		throw new ApiClientError('Network error', 0);
	}
}

export async function apiGet<T>(endpoint: string, params?: Record<string, any>): Promise<T> {
	const queryString = params 
		? '?' + new URLSearchParams(
			Object.entries(params).reduce((acc, [key, value]) => {
				if (value !== null && value !== undefined) {
					acc[key] = String(value);
				}
				return acc;
			}, {} as Record<string, string>)
		).toString()
		: '';
	
	return apiRequest<T>(`${endpoint}${queryString}`, {
		method: 'GET'
	});
}

export async function apiPost<T>(endpoint: string, data?: any, options: RequestInit = {}): Promise<T> {
	return apiRequest<T>(endpoint, {
		method: 'POST',
		body: data ? JSON.stringify(data) : undefined,
		...options
	});
}

export async function apiPatch<T>(endpoint: string, data?: any, options: RequestInit = {}): Promise<T> {
	return apiRequest<T>(endpoint, {
		method: 'PATCH',
		body: data ? JSON.stringify(data) : undefined,
		...options
	});
}

export async function apiPut<T>(endpoint: string, data?: any, options: RequestInit = {}): Promise<T> {
	return apiRequest<T>(endpoint, {
		method: 'PUT',
		body: data ? JSON.stringify(data) : undefined,
		...options
	});
}

export async function apiDelete<T>(endpoint: string): Promise<T> {
	return apiRequest<T>(endpoint, {
		method: 'DELETE'
	});
}


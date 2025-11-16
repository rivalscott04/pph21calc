import { writable } from 'svelte/store';

export interface ToastMessage {
	id: string;
	message: string;
	type: 'success' | 'error' | 'info' | 'warning';
	duration?: number;
}

export const toasts = writable<ToastMessage[]>([]);

let toastIdCounter = 0;

function generateId(): string {
	return `toast-${++toastIdCounter}-${Date.now()}`;
}

export function showToast(
	message: string,
	type: 'success' | 'error' | 'info' | 'warning' = 'info',
	duration: number = 3000
) {
	const id = generateId();
	const toast: ToastMessage = { id, message, type, duration };

	toasts.update((list) => [...list, toast]);

	// Auto remove after duration
	if (duration > 0) {
		setTimeout(() => {
			removeToast(id);
		}, duration);
	}

	return id;
}

export function removeToast(id: string) {
	toasts.update((list) => list.filter((t) => t.id !== id));
}

export function clearToasts() {
	toasts.set([]);
}

// Convenience functions
export const toast = {
	success: (message: string, duration?: number) => showToast(message, 'success', duration),
	error: (message: string, duration?: number) => showToast(message, 'error', duration || 5000),
	info: (message: string, duration?: number) => showToast(message, 'info', duration),
	warning: (message: string, duration?: number) => showToast(message, 'warning', duration)
};


<script lang="ts">
	import { createEventDispatcher, onMount } from 'svelte';

	export let message: string;
	export let type: 'success' | 'error' | 'info' | 'warning' = 'info';
	export let duration: number = 3000;

	const dispatch = createEventDispatcher();

	onMount(() => {
		if (duration > 0) {
			const timer = setTimeout(() => {
				dispatch('close');
			}, duration);
			return () => clearTimeout(timer);
		}
	});

	function handleClose() {
		dispatch('close');
	}

	function getAlertClass() {
		switch (type) {
			case 'success':
				return 'toast-success-brand';
			case 'error':
				return 'toast-error-brand';
			case 'warning':
				return 'alert-warning';
			case 'info':
			default:
				return 'alert-info';
		}
	}
</script>

<div class="alert {getAlertClass()} shadow-lg max-w-md" role="alert">
	<div class="flex items-center gap-3 w-full">
		{#if type === 'success'}
			<!-- Checkmark icon for success -->
			<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
				<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
			</svg>
		{:else if type === 'error'}
			<!-- X icon for error -->
			<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
				<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
			</svg>
		{/if}
		<span class="flex-1 text-white">{message}</span>
		<button class="btn btn-sm btn-ghost btn-circle text-white hover:bg-white/20" on:click={handleClose} aria-label="Close">
			<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
			</svg>
		</button>
	</div>
</div>


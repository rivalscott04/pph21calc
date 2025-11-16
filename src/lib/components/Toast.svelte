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
				return 'alert-success';
			case 'error':
				return 'alert-error';
			case 'warning':
				return 'alert-warning';
			case 'info':
			default:
				return 'alert-info';
		}
	}
</script>

<div class="alert {getAlertClass()} shadow-lg max-w-md" role="alert">
	<div class="flex items-center justify-between w-full">
		<span>{message}</span>
		<button class="btn btn-sm btn-ghost btn-circle" on:click={handleClose} aria-label="Close">
			<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
			</svg>
		</button>
	</div>
</div>


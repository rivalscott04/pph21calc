<script lang="ts">
	import { toasts, removeToast } from '$lib/stores/toast.js';
	import Toast from './Toast.svelte';
</script>

<div class="toast toast-top toast-end z-[9999] flex flex-col gap-3 items-end">
	{#each $toasts as toastItem (toastItem.id)}
		<Toast
			message={toastItem.message}
			type={toastItem.type}
			duration={toastItem.duration}
			on:close={() => removeToast(toastItem.id)}
		/>
	{/each}
</div>


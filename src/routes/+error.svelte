<script lang="ts">
	import { page } from '$app/stores';
	import { goto } from '$app/navigation';
	
	// Get error status from page store
	$: status = $page.status || 404;
	$: message = status === 404 
		? 'Halaman tidak ditemukan' 
		: status >= 500
		? 'Terjadi kesalahan server'
		: 'Terjadi kesalahan';
</script>

<div class="min-h-screen flex items-center justify-center bg-base-100 p-4">
	<div class="text-center space-y-6 max-w-md">
		<!-- 404 GIF Image -->
		<div class="flex justify-center">
			<img 
				src="/404.gif" 
				alt="404 Not Found" 
				class="max-w-full h-auto rounded-lg shadow-lg"
			/>
		</div>
		
		<!-- Error Message -->
		<div class="space-y-2">
			<h1 class="text-6xl font-bold text-primary">{status}</h1>
			<h2 class="text-2xl font-semibold text-base-content">{message}</h2>
			<p class="text-base-content opacity-70">
				{status === 404 
					? 'Halaman yang Anda cari tidak ditemukan atau telah dipindahkan.'
					: 'Maaf, terjadi kesalahan saat memproses permintaan Anda.'}
			</p>
		</div>
		
		<!-- Action Buttons -->
		<div class="flex flex-col sm:flex-row gap-3 justify-center">
			<button 
				class="btn btn-brand"
				onclick={() => goto('/dashboard')}
			>
				<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
				</svg>
				Kembali ke Dashboard
			</button>
			<button 
				class="btn btn-ghost"
				onclick={() => window.history.back()}
			>
				<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
				</svg>
				Kembali
			</button>
		</div>
	</div>
</div>

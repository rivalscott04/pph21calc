<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { auth } from '$lib/stores/auth.js';
	import { authApi } from '$lib/api/auth.js';
	import { toast } from '$lib/stores/toast.js';
	
	let email = '';
	let password = '';
	let loading = false;
	let showPassword = false;
	let formError = '';
	
	onMount(() => {
		// Don't auto-redirect on login page
		// User should explicitly login even if there's stored token
		// Token will be verified when accessing protected routes
		
		const unsubscribe = auth.subscribe((state) => {
			// Only redirect if user is already authenticated (fresh login)
			// Don't redirect if just loaded from storage (might be expired)
			if (state.isAuthenticated && state.user && state.token) {
				// Small delay to ensure auth state is fully set
				setTimeout(() => {
					goto('/dashboard');
				}, 100);
			}
		});
		
		return () => unsubscribe();
	});
	
	async function handleSubmit(event: SubmitEvent) {
		event.preventDefault();
		
		if (!email || !password) {
			formError = 'Email dan password wajib diisi.';
			return;
		}
		
		loading = true;
		formError = '';
		
		try {
			await authApi.login(email, password);
			await goto('/dashboard');
		} catch (error) {
			if (error instanceof Error && error.message) {
				formError = error.message;
			} else {
				formError = 'Gagal login. Silakan coba lagi.';
			}
			toast.error(formError);
		} finally {
			loading = false;
		}
	}
</script>

<svelte:head>
	<title>Masuk | PPH21 System</title>
</svelte:head>

<div class="min-h-screen bg-gradient-to-br from-base-200 via-base-100 to-base-200 flex items-center justify-center px-4 py-10">
	<div class="w-full max-w-5xl">
		<div class="bg-base-100 rounded-3xl shadow-2xl grid lg:grid-cols-2 overflow-hidden border border-base-200">
			<!-- Illustration -->
			<div class="hidden lg:flex bg-primary/5 items-center justify-center p-10">
				<img src="/login.svg" alt="Login Illustration" class="max-h-96 w-full object-contain drop-shadow-lg" />
			</div>
			
			<!-- Form -->
			<div class="p-8 sm:p-12 space-y-8">
				<div class="space-y-2">
					<p class="text-sm uppercase tracking-[0.3em] text-primary font-semibold">Selamat datang kembali</p>
					<h1 class="text-3xl sm:text-4xl font-bold text-base-content leading-tight">Masuk ke PPH21 System</h1>
					<p class="text-base-content/80 text-sm sm:text-base">Gunakan kredensial yang sudah terdaftar untuk melanjutkan pengelolaan payroll & PPh21.</p>
				</div>
				
				<form class="space-y-6" on:submit|preventDefault={handleSubmit}>
					<label class="form-control w-full">
						<div class="label">
							<span class="label-text text-base-content font-semibold">Email</span>
						</div>
						<input
							type="email"
							class="input input-bordered border-base-300 focus:border-primary focus:ring-2 focus:ring-primary/20 w-full text-base-content placeholder:text-base-content/40"
							placeholder="admin@test.local"
							bind:value={email}
							required
						/>
					</label>
					
					<label class="form-control w-full space-y-2">
						<div class="label">
							<span class="label-text text-base-content font-semibold">Password</span>
						</div>
						<div class="relative">
							<input
								type={showPassword ? 'text' : 'password'}
								class="input input-bordered border-base-300 focus:border-primary focus:ring-2 focus:ring-primary/20 w-full pr-12 text-base-content placeholder:text-base-content/40 tracking-wider"
								placeholder="••••••••"
								bind:value={password}
								required
							/>
							<button
								type="button"
								class="btn btn-ghost btn-xs text-base-content/60 absolute right-2 top-1/2 -translate-y-1/2"
								on:click={() => (showPassword = !showPassword)}
								aria-label={showPassword ? 'Sembunyikan password' : 'Tampilkan password'}
							>
								{#if showPassword}
									<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.5 0-8.307-2.672-10-6 1.091-2.349 3.07-4.263 5.5-5.27m3-1.048A9.954 9.954 0 0112 5c4.5 0 8.307 2.672 10 6a11.16 11.16 0 01-1.62 2.472M9.88 9.88a3 3 0 104.24 4.24" />
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18" />
									</svg>
								{:else}
									<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
									</svg>
								{/if}
							</button>
						</div>
					</label>
					
					{#if formError}
						<div class="alert alert-error text-sm">
							{formError}
						</div>
					{/if}
					
					<button
						class="btn btn-success btn-lg w-full text-base font-semibold text-white mt-2 disabled:bg-success/70 disabled:text-white disabled:border-success/70 disabled:opacity-80"
						type="submit"
						disabled={loading}
					>
						{#if loading}
							<span class="loading loading-spinner loading-sm"></span>
							Sedang masuk...
						{:else}
							Masuk
						{/if}
					</button>
				</form>
				
				<div class="space-y-3">
					<div class="divider text-base-content/60 text-sm uppercase tracking-[0.3em]">Akses cepat</div>
					<div class="grid sm:grid-cols-2 gap-3">
						<button
							class="btn btn-success btn-sm sm:btn-md font-semibold text-white"
							type="button"
							on:click={() => {
								email = 'admin@test.local';
								password = 'password';
							}}
						>
							Superadmin Demo
						</button>
						<button
							class="btn btn-success btn-sm sm:btn-md font-semibold text-white"
							type="button"
							on:click={() => {
								email = 'tenant_admin@test.local';
								password = 'password';
							}}
						>
							Tenant Admin Demo
						</button>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script lang="ts">
	import { page } from '$app/stores';
	import { goto } from '$app/navigation';
	import { onMount } from 'svelte';
	import { get } from 'svelte/store';
	import Toaster from '$lib/components/Toaster.svelte';
	import { auth, type User, type Tenant } from '$lib/stores/auth.js';
	import { authApi } from '$lib/api/auth.js';
	import { toast } from '$lib/stores/toast.js';
	import { isCoreTaxEnabled } from '$lib/stores/modules.js';
	import { canAccessMenu } from '$lib/utils/permissions.js';
	
	const coreTaxEnabled = isCoreTaxEnabled;
	
	let checkingAuth = true;
	let isAuthenticated = false;
	let currentUser: User | null = null;
	let currentTenant: Tenant | null = null;
	
	async function handleLogout() {
		try {
			await authApi.logout();
			await goto('/login');
		} catch (error) {
			console.error('Logout error:', error);
			// Force logout even if API call fails
			auth.clearAuth();
			await goto('/login');
		}
	}
	
	onMount(() => {
		let hasRedirected = false;
		
		// Load auth from storage first
		const hasStoredAuth = auth.loadFromStorage();
		
		// Initialize brand theme watcher - will reload when tenant changes
		import('$lib/stores/brand.js').then(({ watchTenantAndReloadTheme, initBrandTheme }) => {
			watchTenantAndReloadTheme();
			// Initial load
			initBrandTheme().catch((error) => {
				console.error('Failed to initialize brand theme:', error);
			});
		});
		
		// If we have a token, verify it with backend
		if (hasStoredAuth) {
			checkingAuth = true;
			authApi.me()
				.then(({ user, tenant }) => {
					// Token is valid, update auth state
					const token = auth.getToken();
					if (token) {
						auth.setAuth(user, tenant, token);
					}
					// Mark as done checking
					checkingAuth = false;
				})
				.catch((error: any) => {
					// Token is invalid or expired
					console.warn('Token verification failed:', error);
					auth.clearAuth();
					checkingAuth = false;
					
					// Only show toast and redirect once
					if (!hasRedirected) {
						hasRedirected = true;
						toast.error('Session expired. Please login again.');
						goto('/login');
					}
				});
		} else {
			// No stored auth, mark as not authenticated
			checkingAuth = false;
		}
		
		// Watch for auth state changes and redirect if needed
		const unsubscribe = auth.subscribe((state) => {
			// Update local state
			isAuthenticated = state.isAuthenticated;
			currentUser = state.user;
			currentTenant = state.tenant;
			
			// Only redirect if not authenticated and not already redirected
			if (!state.isAuthenticated && !hasRedirected && !checkingAuth) {
				hasRedirected = true;
				// Small delay to prevent race condition
				setTimeout(() => {
					if (typeof window !== 'undefined' && !window.location.pathname.startsWith('/login')) {
						goto('/login');
					}
				}, 100);
			}
		});
		
		return () => {
			unsubscribe();
		};
	});
	
	// Layout dengan sidebar untuk aplikasi
	// Semua warna menggunakan brand colors (primary, secondary, accent, dll)
	// Sidebar collapsible di desktop menggunakan is-drawer-open/is-drawer-close
	
	// Helper function to close drawer on mobile when menu item is clicked
	function closeDrawerOnMobile() {
		if (typeof window !== 'undefined' && window.innerWidth < 1024) {
			const drawerToggle = document.getElementById('app-drawer') as HTMLInputElement;
			if (drawerToggle) {
				drawerToggle.checked = false;
			}
		}
	}
</script>

{#if checkingAuth}
<div class="min-h-screen flex items-center justify-center bg-base-100">
	<span class="loading loading-spinner loading-lg text-primary"></span>
</div>
{:else if isAuthenticated}
<div class="drawer lg:drawer-open">
	<input id="app-drawer" type="checkbox" class="drawer-toggle" checked />
	<div class="drawer-content flex flex-col">
		<!-- Topbar dengan toggle button untuk desktop -->
		<div class="navbar bg-base-100 shadow-sm border-b border-base-300">
			<div class="flex-none lg:hidden">
				<label for="app-drawer" class="btn btn-ghost btn-square">
					<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-6 h-6 stroke-current text-neutral">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
					</svg>
				</label>
			</div>
			<div class="flex-1">
				<h1 class="text-lg font-bold text-base-content">PPH21 System</h1>
			</div>
			<div class="flex-none">
				{#if currentUser}
					<details class="dropdown dropdown-end">
						<summary class="btn btn-ghost gap-2">
							<div class="avatar placeholder">
								<div class="bg-primary text-primary-content rounded-full w-8 flex items-center justify-center">
									<span class="text-xs font-bold">
										{currentUser.name.split(' ').map((n: string) => n[0]).join('').toUpperCase().slice(0, 2)}
									</span>
								</div>
							</div>
							<span class="hidden sm:inline text-base-content font-medium">{currentUser.name}</span>
							<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-base-content" fill="none" viewBox="0 0 24 24" stroke="currentColor">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
							</svg>
						</summary>
						<ul class="dropdown-content menu bg-base-100 rounded-box z-[1] w-52 p-2 shadow-lg border border-base-300 mt-2">
							<li class="menu-title">
								<span>{currentUser.name}</span>
								<span class="text-xs opacity-70">{currentUser.email}</span>
							</li>
							{#if currentTenant}
								<li class="menu-title">
									<span class="text-xs opacity-70">Tenant: {currentTenant.name}</span>
								</li>
							{/if}
							<li>
								<button class="text-base-content" on:click={handleLogout}>
									<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
									</svg>
									Logout
								</button>
							</li>
						</ul>
					</details>
				{/if}
			</div>
		</div>
		<main class="flex-1 p-4 bg-base-100">
			<slot />
		</main>
	</div>
	<div class="drawer-side is-drawer-close:overflow-visible">
		<label for="app-drawer" aria-label="close sidebar" class="drawer-overlay"></label>
		<aside class="is-drawer-close:w-16 is-drawer-open:w-72 bg-base-200 border-r border-base-300 flex flex-col items-start min-h-full transition-all duration-300">
			<!-- Sidebar header dengan toggle button -->
			<div class="flex items-center justify-between w-full p-4 border-b border-base-300 bg-base-100">
				<h2 class="text-xl font-bold text-base-content is-drawer-close:hidden">PPH21</h2>
				<label for="app-drawer" class="btn btn-ghost btn-circle btn-sm is-drawer-close:tooltip is-drawer-close:tooltip-right" data-tip="Toggle sidebar">
					<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-5 h-5 stroke-current text-neutral">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
					</svg>
				</label>
			</div>
			
			<!-- Menu items -->
			<ul class="menu w-full grow p-2 gap-1 text-base-content">
				<!-- Dashboard - semua role bisa akses -->
				<li>
					<a 
						href="/dashboard" 
						class="is-drawer-close:tooltip is-drawer-close:tooltip-right {$page.url.pathname === '/dashboard' ? 'active' : ''}" 
						data-tip="Dashboard"
						on:click={closeDrawerOnMobile}
					>
						<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
						</svg>
						<span class="is-drawer-close:hidden">Dashboard</span>
					</a>
				</li>
				
				<!-- Payroll - TENANT_ADMIN, HR, FINANCE -->
				{#if canAccessMenu('payroll')}
				<li>
					<a 
						href="/payroll" 
						class="is-drawer-close:tooltip is-drawer-close:tooltip-right {$page.url.pathname === '/payroll' ? 'active' : ''}" 
						data-tip="Payroll"
						on:click={closeDrawerOnMobile}
					>
						<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
						</svg>
						<span class="is-drawer-close:hidden">Payroll</span>
					</a>
				</li>
				{/if}
				
				<!-- Calculator - TENANT_ADMIN, HR, FINANCE -->
				{#if canAccessMenu('calculator')}
				<li>
					<details class="group">
						<summary class={`is-drawer-close:tooltip is-drawer-close:tooltip-right {$page.url.pathname.startsWith('/calculator') ? 'active' : ''}`} data-tip="Kalkulator">
							<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-5m-6 5h.01M9 17h6m0 0V9m0 8h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
							</svg>
							<span class="is-drawer-close:hidden">Kalkulator</span>
							<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-auto is-drawer-close:hidden transition-transform group-open:rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
							</svg>
						</summary>
						<ul class="ml-4 is-drawer-close:hidden">
							<li>
								<a 
									href="/calculator" 
									class={$page.url.pathname === '/calculator' ? 'active' : ''}
									on:click={closeDrawerOnMobile}
								>
									Hitung PPh 21
								</a>
							</li>
							<li>
								<a 
									href="/calculator/history" 
									class={$page.url.pathname === '/calculator/history' ? 'active' : ''}
									on:click={closeDrawerOnMobile}
								>
									Riwayat Perhitungan
								</a>
							</li>
						</ul>
					</details>
				</li>
				{/if}
				
				<!-- CoreTax - TENANT_ADMIN, FINANCE (dan module enabled) -->
				{#if $coreTaxEnabled && canAccessMenu('coretax')}
				<li>
					<a 
						href="/coretax" 
						class="is-drawer-close:tooltip is-drawer-close:tooltip-right {$page.url.pathname === '/coretax' ? 'active' : ''}" 
						data-tip="CoreTax"
						on:click={closeDrawerOnMobile}
					>
						<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
						</svg>
						<span class="is-drawer-close:hidden">CoreTax</span>
					</a>
				</li>
				{/if}
				
				<!-- Master Data - TENANT_ADMIN, HR -->
				{#if canAccessMenu('master/persons')}
				<li class="menu-title is-drawer-close:hidden text-base-content opacity-70">
					<span>Master Data</span>
				</li>
				<li>
					<a 
						href="/master/persons" 
						class="is-drawer-close:tooltip is-drawer-close:tooltip-right {$page.url.pathname.startsWith('/master/persons') ? 'active' : ''}" 
						data-tip="Pegawai"
						on:click={closeDrawerOnMobile}
					>
						<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
						</svg>
						<span class="is-drawer-close:hidden">Pegawai</span>
					</a>
				</li>
				<li>
					<a 
						href="/master/components" 
						class="is-drawer-close:tooltip is-drawer-close:tooltip-right {$page.url.pathname.startsWith('/master/components') ? 'active' : ''}" 
						data-tip="Komponen Penghasilan"
						on:click={closeDrawerOnMobile}
					>
						<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
						</svg>
						<span class="is-drawer-close:hidden">Komponen Penghasilan</span>
					</a>
				</li>
				<li>
					<a 
						href="/master/deduction-components" 
						class="is-drawer-close:tooltip is-drawer-close:tooltip-right {$page.url.pathname.startsWith('/master/deduction-components') ? 'active' : ''}" 
						data-tip="Komponen Pengurang"
						on:click={closeDrawerOnMobile}
					>
						<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
						</svg>
						<span class="is-drawer-close:hidden">Komponen Pengurang</span>
					</a>
				</li>
				{/if}
				
				<!-- Settings - hanya TENANT_ADMIN -->
				{#if canAccessMenu('settings/branding')}
				<li class="menu-title is-drawer-close:hidden text-base-content opacity-70">
					<span>Settings</span>
				</li>
				<li>
					<a 
						href="/settings/branding" 
						class="is-drawer-close:tooltip is-drawer-close:tooltip-right {$page.url.pathname === '/settings/branding' ? 'active' : ''}" 
						data-tip="Branding"
						on:click={closeDrawerOnMobile}
					>
						<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
						</svg>
						<span class="is-drawer-close:hidden">Branding</span>
					</a>
				</li>
				<li>
					<a 
						href="/settings/id-schemes" 
						class="is-drawer-close:tooltip is-drawer-close:tooltip-right {$page.url.pathname === '/settings/id-schemes' ? 'active' : ''}" 
						data-tip="ID Schemes"
						on:click={closeDrawerOnMobile}
					>
						<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
						</svg>
						<span class="is-drawer-close:hidden">ID Schemes</span>
					</a>
				</li>
				<li>
					<a 
						href="/settings/modules" 
						class="is-drawer-close:tooltip is-drawer-close:tooltip-right {$page.url.pathname === '/settings/modules' ? 'active' : ''}" 
						data-tip="Modules"
						on:click={closeDrawerOnMobile}
					>
						<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
						</svg>
						<span class="is-drawer-close:hidden">Modules</span>
					</a>
				</li>
				<li>
					<a 
						href="/settings/users" 
						class="is-drawer-close:tooltip is-drawer-close:tooltip-right {$page.url.pathname === '/settings/users' ? 'active' : ''}" 
						data-tip="Users"
						on:click={closeDrawerOnMobile}
					>
						<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
						</svg>
						<span class="is-drawer-close:hidden">Users</span>
					</a>
				</li>
				{/if}
			</ul>
		</aside>
	</div>
</div>
{:else}
<div class="min-h-screen bg-base-100"></div>
{/if}

<!-- Toast notifications -->
<Toaster />

<style>
	/* Ensure SVG icons in btn-ghost buttons turn white on hover */
	.btn-ghost:hover svg,
	.btn-ghost:hover svg path {
		color: hsl(0 0% 100%) !important;
		stroke: hsl(0 0% 100%) !important;
	}
</style>
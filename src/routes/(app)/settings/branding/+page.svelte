<script lang="ts">
	import { onMount } from 'svelte';
	import { configApi } from '$lib/api/config.js';
	import { loadBrandColorsFromAPI, updateBrandColorsViaAPI, applyBrandTheme, type BrandColors } from '$lib/stores/brand.js';
	import { auth } from '$lib/stores/auth.js';
	import { get } from 'svelte/store';
	import { toast } from '$lib/stores/toast.js';

	let loading = true;
	let saving = false;
	let colors: BrandColors = {
		primary: '#0ea5e9',
		secondary: '#10b981',
		accent: '#f59e0b',
		neutral: '#3d4451',
		base100: '#1e293b',
		button: '#0ea5e9',
		badge: '#3d4451'
	};

	// Validate HEX color
	function isValidHex(hex: string): boolean {
		return /^#[0-9A-Fa-f]{6}$/.test(hex);
	}

	// Update color and apply preview
	function updateColor(key: keyof BrandColors, value: string) {
		if (value.startsWith('#')) {
			colors[key] = value.toUpperCase();
			// Apply preview immediately
			applyBrandTheme(colors);
		}
	}

	// Reset to default
	function resetToDefault() {
		colors = {
			primary: '#0ea5e9',
			secondary: '#10b981',
			accent: '#f59e0b',
			neutral: '#3d4451',
			base100: '#1e293b',
			button: '#0ea5e9',
			badge: '#3d4451'
		};
		applyBrandTheme(colors);
		toast.info('Warna direset ke default');
	}

	// Save colors
	async function saveColors() {
		// Validate all colors
		for (const [key, value] of Object.entries(colors)) {
			if (!isValidHex(value)) {
				toast.error(`Warna ${key} tidak valid. Format harus #RRGGBB`);
				return;
			}
		}

		saving = true;
		try {
			await updateBrandColorsViaAPI(colors);
			toast.success('Branding berhasil disimpan dan diterapkan');
		} catch (error) {
			console.error('Failed to save branding:', error);
		} finally {
			saving = false;
		}
	}

	onMount(async () => {
		try {
			const user = get(auth);
			const tenantId = user?.tenant?.id;

			if (tenantId) {
				const apiColors = await loadBrandColorsFromAPI(tenantId);
				if (apiColors) {
					colors = apiColors;
					applyBrandTheme(colors);
				}
			}
		} catch (error) {
			console.error('Failed to load branding:', error);
			toast.error('Gagal memuat konfigurasi branding');
		} finally {
			loading = false;
		}
	});
</script>

<div class="space-y-6">
	<div class="flex justify-between items-center">
		<div>
			<h1 class="text-3xl font-bold text-base-content">Branding & Tema</h1>
			<p class="text-base-content opacity-70 mt-1">Kustomisasi warna tema aplikasi sesuai brand Anda</p>
		</div>
	</div>

	{#if loading}
		<div class="flex justify-center items-center min-h-[400px]">
			<span class="loading loading-spinner loading-lg text-primary"></span>
		</div>
	{:else}
		<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
			<!-- Form Input Colors -->
			<div class="card bg-base-100 shadow-lg">
				<div class="card-body">
					<h2 class="card-title text-base-content">Warna Brand</h2>
					<p class="text-sm text-base-content opacity-70 mb-4">
						Masukkan kode warna HEX (contoh: #0ea5e9) untuk setiap warna brand
					</p>

					<div class="space-y-4">
						<!-- Primary Color -->
						<label class="form-control">
							<div class="label">
								<span class="label-text font-semibold text-base-content">Warna Primary</span>
								<span class="label-text-alt text-base-content opacity-60">Warna utama brand</span>
							</div>
							<div class="flex gap-3 items-center">
								<input
									type="color"
									class="w-16 h-16 rounded-lg border-2 border-base-300 cursor-pointer"
									bind:value={colors.primary}
									on:input={(e) => updateColor('primary', e.currentTarget.value)}
								/>
								<input
									type="text"
									class="input input-bordered flex-1 font-mono"
									placeholder="#0ea5e9"
									bind:value={colors.primary}
									on:input={(e) => updateColor('primary', e.currentTarget.value)}
									maxlength="7"
								/>
							</div>
						</label>

						<!-- Secondary Color -->
						<label class="form-control">
							<div class="label">
								<span class="label-text font-semibold text-base-content">Warna Secondary</span>
								<span class="label-text-alt text-base-content opacity-60">Warna sekunder brand</span>
							</div>
							<div class="flex gap-3 items-center">
								<input
									type="color"
									class="w-16 h-16 rounded-lg border-2 border-base-300 cursor-pointer"
									bind:value={colors.secondary}
									on:input={(e) => updateColor('secondary', e.currentTarget.value)}
								/>
								<input
									type="text"
									class="input input-bordered flex-1 font-mono"
									placeholder="#10b981"
									bind:value={colors.secondary}
									on:input={(e) => updateColor('secondary', e.currentTarget.value)}
									maxlength="7"
								/>
							</div>
						</label>

						<!-- Accent Color -->
						<label class="form-control">
							<div class="label">
								<span class="label-text font-semibold text-base-content">Warna Accent</span>
								<span class="label-text-alt text-base-content opacity-60">Warna aksen/highlight</span>
							</div>
							<div class="flex gap-3 items-center">
								<input
									type="color"
									class="w-16 h-16 rounded-lg border-2 border-base-300 cursor-pointer"
									bind:value={colors.accent}
									on:input={(e) => updateColor('accent', e.currentTarget.value)}
								/>
								<input
									type="text"
									class="input input-bordered flex-1 font-mono"
									placeholder="#f59e0b"
									bind:value={colors.accent}
									on:input={(e) => updateColor('accent', e.currentTarget.value)}
									maxlength="7"
								/>
							</div>
						</label>

						<!-- Neutral Color -->
						<label class="form-control">
							<div class="label">
								<span class="label-text font-semibold text-base-content">Warna Neutral</span>
								<span class="label-text-alt text-base-content opacity-60">Warna netral/abu-abu</span>
							</div>
							<div class="flex gap-3 items-center">
								<input
									type="color"
									class="w-16 h-16 rounded-lg border-2 border-base-300 cursor-pointer"
									bind:value={colors.neutral}
									on:input={(e) => updateColor('neutral', e.currentTarget.value)}
								/>
								<input
									type="text"
									class="input input-bordered flex-1 font-mono"
									placeholder="#3d4451"
									bind:value={colors.neutral}
									on:input={(e) => updateColor('neutral', e.currentTarget.value)}
									maxlength="7"
								/>
							</div>
						</label>

						<!-- Base100 Color -->
						<label class="form-control">
							<div class="label">
								<span class="label-text font-semibold text-base-content">Warna Background</span>
								<span class="label-text-alt text-base-content opacity-60">Warna latar belakang utama</span>
							</div>
							<div class="flex gap-3 items-center">
								<input
									type="color"
									class="w-16 h-16 rounded-lg border-2 border-base-300 cursor-pointer"
									bind:value={colors.base100}
									on:input={(e) => updateColor('base100', e.currentTarget.value)}
								/>
								<input
									type="text"
									class="input input-bordered flex-1 font-mono"
									placeholder="#1e293b"
									bind:value={colors.base100}
									on:input={(e) => updateColor('base100', e.currentTarget.value)}
									maxlength="7"
								/>
							</div>
						</label>

						<!-- Button Color -->
						<label class="form-control">
							<div class="label">
								<span class="label-text font-semibold text-base-content">Warna Button</span>
								<span class="label-text-alt text-base-content opacity-60">Warna untuk komponen button</span>
							</div>
							<div class="flex gap-3 items-center">
								<input
									type="color"
									class="w-16 h-16 rounded-lg border-2 border-base-300 cursor-pointer"
									bind:value={colors.button}
									on:input={(e) => updateColor('button', e.currentTarget.value)}
								/>
								<input
									type="text"
									class="input input-bordered flex-1 font-mono"
									placeholder="#0ea5e9"
									bind:value={colors.button}
									on:input={(e) => updateColor('button', e.currentTarget.value)}
									maxlength="7"
								/>
							</div>
						</label>

						<!-- Badge Color -->
						<label class="form-control">
							<div class="label">
								<span class="label-text font-semibold text-base-content">Warna Badge</span>
								<span class="label-text-alt text-base-content opacity-60">Warna default untuk komponen badge</span>
							</div>
							<div class="flex gap-3 items-center">
								<input
									type="color"
									class="w-16 h-16 rounded-lg border-2 border-base-300 cursor-pointer"
									bind:value={colors.badge}
									on:input={(e) => updateColor('badge', e.currentTarget.value)}
								/>
								<input
									type="text"
									class="input input-bordered flex-1 font-mono"
									placeholder="#3d4451"
									bind:value={colors.badge}
									on:input={(e) => updateColor('badge', e.currentTarget.value)}
									maxlength="7"
								/>
							</div>
						</label>

						<!-- Action Buttons -->
						<div class="flex gap-3 pt-4">
							<button
								class="btn btn-success flex-1 text-white"
								on:click={saveColors}
								disabled={saving}
							>
								{#if saving}
									<span class="loading loading-spinner loading-sm"></span>
									Menyimpan...
								{:else}
									<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
									</svg>
									Simpan & Terapkan
								{/if}
							</button>
							<button
								class="btn btn-ghost"
								on:click={resetToDefault}
							>
								<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
								</svg>
								Reset
							</button>
						</div>
					</div>
				</div>
			</div>

			<!-- Preview -->
			<div class="card bg-base-100 shadow-lg">
				<div class="card-body">
					<h2 class="card-title text-base-content">Preview Tema</h2>
					<p class="text-sm text-base-content opacity-70 mb-4">
						Lihat bagaimana warna brand diterapkan pada komponen UI
					</p>

					<div class="space-y-4">
						<!-- Color Swatches -->
						<div class="grid grid-cols-2 gap-3">
							<div class="p-4 rounded-lg" style="background-color: {colors.primary};">
								<p class="text-white font-semibold">Primary</p>
								<p class="text-white/80 text-sm">{colors.primary}</p>
							</div>
							<div class="p-4 rounded-lg" style="background-color: {colors.secondary};">
								<p class="text-white font-semibold">Secondary</p>
								<p class="text-white/80 text-sm">{colors.secondary}</p>
							</div>
							<div class="p-4 rounded-lg" style="background-color: {colors.accent};">
								<p class="text-white font-semibold">Accent</p>
								<p class="text-white/80 text-sm">{colors.accent}</p>
							</div>
							<div class="p-4 rounded-lg border-2 border-base-300" style="background-color: {colors.neutral};">
								<p class="text-white font-semibold">Neutral</p>
								<p class="text-white/80 text-sm">{colors.neutral}</p>
							</div>
						</div>

						<!-- Component Preview -->
						<div class="space-y-3 pt-4 border-t border-base-300">
							<h3 class="font-semibold text-base-content">Preview Komponen</h3>
							
							<!-- Buttons Preview -->
							<div class="flex flex-wrap gap-2">
								<button class="btn btn-brand">Button Custom (Brand)</button>
								<button class="btn btn-primary text-white">Button Primary</button>
								<button class="btn btn-secondary text-white">Button Secondary</button>
								<button class="btn btn-accent text-white">Button Accent</button>
							</div>

							<!-- Card Preview -->
							<div class="card bg-base-200">
								<div class="card-body p-4">
									<h4 class="card-title text-base-content text-sm">Card Example</h4>
									<p class="text-base-content opacity-70 text-sm">
										Ini adalah contoh card dengan background base-200
									</p>
								</div>
							</div>

							<!-- Alert Preview -->
							<div class="alert alert-info">
								<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
								</svg>
								<span class="text-sm">Ini adalah contoh alert dengan warna info</span>
							</div>

							<!-- Badge Preview -->
							<div class="flex flex-wrap gap-2">
								<span class="badge badge-brand">Badge Custom (Brand)</span>
								<span class="badge badge-primary">Badge Primary</span>
								<span class="badge badge-secondary">Badge Secondary</span>
								<span class="badge badge-accent">Badge Accent</span>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	{/if}
</div>

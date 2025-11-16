<script lang="ts">
	import { onMount } from 'svelte';
	import { configApi, type IdentifierScheme } from '$lib/api/config.js';
	import { auth } from '$lib/stores/auth.js';
	import { get } from 'svelte/store';
	import { toast } from '$lib/stores/toast.js';

	let loading = true;
	let schemes: IdentifierScheme[] = [];
	let showModal = false;
	let editingScheme: IdentifierScheme | null = null;
	let saving = false;
	let showDeleteModal = false;
	let schemeToDelete: IdentifierScheme | null = null;

	// Form state
	let formData = {
		code: '',
		label: '',
		prefix: '', // Prefix untuk ID (contoh: "NTB")
		format_type: 'NUMERIC', // NUMERIC atau ALPHANUMERIC
		length_min: '',
		length_max: ''
	};

	// Form errors
	let formErrors: Record<string, string> = {};
let codeInputWarning = '';
	function handleCodeInput(event: Event) {
		const input = event.currentTarget as HTMLInputElement;
		const sanitized = input.value.replace(/[^a-zA-Z0-9_]/g, '').toUpperCase();
	codeInputWarning = input.value !== sanitized ? 'Hanya huruf besar, angka, dan underscore yang diizinkan' : '';
		input.value = sanitized;
		formData.code = sanitized;
	}

	// Validation rules
	const normalizeRules = [
		{ value: 'NONE', label: 'Tidak ada normalisasi' },
		{ value: 'NUMERIC', label: 'Hanya angka' },
		{ value: 'ALNUM', label: 'Huruf dan angka' },
		{ value: 'UPPER', label: 'Huruf besar' }
	];


	function openCreateModal() {
		editingScheme = null;
		formData = {
			code: '',
			label: '',
			prefix: '',
			format_type: 'NUMERIC',
			length_min: '',
			length_max: ''
		};
		formErrors = {};
		codeInputWarning = '';
		showModal = true;
	}

	function openEditModal(scheme: IdentifierScheme) {
		editingScheme = scheme;
		
		// Detect format type from normalize rule
		let formatType = 'NUMERIC';
		if (scheme.normalize_rule === 'ALNUM') {
			formatType = 'ALPHANUMERIC';
		}
		
		formData = {
			code: scheme.code,
			label: scheme.label,
			prefix: scheme.prefix || '',
			format_type: formatType,
			length_min: scheme.length_min?.toString() || '',
			length_max: scheme.length_max?.toString() || ''
		};
		formErrors = {};
		codeInputWarning = '';
		showModal = true;
	}

	function closeModal() {
		showModal = false;
		editingScheme = null;
		formData = {
			code: '',
			label: '',
			prefix: '',
			format_type: 'NUMERIC',
			length_min: '',
			length_max: ''
		};
		formErrors = {};
		codeInputWarning = '';
	}

	function validateForm(): boolean {
		formErrors = {};

		if (!formData.code.trim()) {
			formErrors.code = 'Kode wajib diisi';
		} else if (!/^[A-Z0-9_]+$/.test(formData.code)) {
			formErrors.code = 'Kode hanya boleh huruf besar, angka, dan underscore';
		}

		if (!formData.label.trim()) {
			formErrors.label = 'Label wajib diisi';
		}

		if (!formData.prefix.trim()) {
			formErrors.prefix = 'Prefix wajib diisi';
		} else if (!/^[A-Z0-9]+$/.test(formData.prefix.toUpperCase())) {
			formErrors.prefix = 'Prefix hanya boleh huruf dan angka';
		}

		if (!formData.length_min || !formData.length_max) {
			if (!formData.length_min) {
				formErrors.length_min = 'Panjang minimum wajib diisi';
			}
			if (!formData.length_max) {
				formErrors.length_max = 'Panjang maksimum wajib diisi';
			}
		} else {
			const min = parseInt(formData.length_min);
			const max = parseInt(formData.length_max);
			
			if (isNaN(min) || min < 1) {
				formErrors.length_min = 'Panjang minimum harus angka positif';
			}
			if (isNaN(max) || max < 1) {
				formErrors.length_max = 'Panjang maksimum harus angka positif';
			}
			if (!isNaN(min) && !isNaN(max) && min > max) {
				formErrors.length_max = 'Panjang maksimum harus lebih besar atau sama dengan panjang minimum';
			}
		}

		return Object.keys(formErrors).length === 0;
	}

	function getFormatRegex(): string {
		const min = formData.length_min ? parseInt(formData.length_min) : 1;
		const max = formData.length_max ? parseInt(formData.length_max) : min;
		
		switch (formData.format_type) {
			case 'NUMERIC':
				return `^[0-9]{${min === max ? min : `${min},${max}`}}$`;
			case 'ALPHANUMERIC':
				return `^[A-Za-z0-9]{${min === max ? min : `${min},${max}`}}$`;
			default:
				return '';
		}
	}

	function getNormalizeRule(): string {
		switch (formData.format_type) {
			case 'NUMERIC':
				return 'NUMERIC';
			case 'ALPHANUMERIC':
				return 'ALNUM';
			default:
				return 'NONE';
		}
	}

	function getExample(): string {
		const min = formData.length_min ? parseInt(formData.length_min) : 8;
		switch (formData.format_type) {
			case 'NUMERIC':
				return '1'.repeat(min);
			case 'ALPHANUMERIC':
				return 'A' + '1'.repeat(min - 1);
			default:
				return '';
		}
	}

	function isValidRegex(pattern: string): boolean {
		try {
			new RegExp(pattern);
			return true;
		} catch {
			return false;
		}
	}

	async function saveScheme() {
		if (!validateForm()) {
			// Get first error message for toast
			const firstError = Object.values(formErrors)[0];
			if (firstError) {
				toast.error(firstError);
			} else {
				toast.error('Mohon perbaiki error pada form');
			}
			return;
		}

		saving = true;
		try {
			const user = get(auth);
			const tenantId = user?.tenant?.id;

		// Kirim data sederhana, backend yang auto-generate
		const schemeData: any = {
			code: formData.code.trim().toUpperCase(),
			label: formData.label.trim(),
			prefix: formData.prefix.trim().toUpperCase(),
			format_type: formData.format_type,
			length_min: parseInt(formData.length_min),
			length_max: parseInt(formData.length_max)
		};

		if (editingScheme) {
			await configApi.updateIdentifierScheme(editingScheme.id, schemeData, tenantId);
			toast.success('Skema ID berhasil diperbarui');
		} else {
			await configApi.createIdentifierScheme(schemeData, tenantId);
			toast.success('Skema ID berhasil dibuat');
		}

			closeModal();
			await loadSchemes();
		} catch (error: any) {
			console.error('Failed to save scheme:', error);
			if (error.errors) {
				formErrors = error.errors;
				// Show first error in toast
				const firstError = Object.values(error.errors)[0];
				if (firstError && typeof firstError === 'string') {
					toast.error(firstError);
				} else if (Array.isArray(firstError) && firstError.length > 0) {
					toast.error(firstError[0]);
				} else {
					toast.error('Mohon perbaiki error pada form');
				}
			} else {
				const errorMsg = error.message || 'Gagal menyimpan skema ID';
				toast.error(errorMsg);
			}
		} finally {
			saving = false;
		}
	}

	function openDeleteModal(scheme: IdentifierScheme) {
		schemeToDelete = scheme;
		showDeleteModal = true;
	}

	function closeDeleteModal() {
		showDeleteModal = false;
		schemeToDelete = null;
	}

	async function confirmDelete() {
		if (!schemeToDelete) return;

		try {
			const user = get(auth);
			const tenantId = user?.tenant?.id;
			await configApi.deleteIdentifierScheme(schemeToDelete.id, tenantId);
			toast.success('Skema ID berhasil dihapus');
			closeDeleteModal();
			await loadSchemes();
		} catch (error) {
			console.error('Failed to delete scheme:', error);
			toast.error('Gagal menghapus skema ID');
		}
	}

	async function loadSchemes() {
		try {
			const user = get(auth);
			const tenantId = user?.tenant?.id;
			schemes = await configApi.getIdentifierSchemes(undefined, tenantId);
		} catch (error) {
			console.error('Failed to load schemes:', error);
			toast.error('Gagal memuat daftar skema ID');
		}
	}

	onMount(async () => {
		await loadSchemes();
		loading = false;
	});
</script>

<div class="space-y-6">
	<div class="flex justify-between items-center">
		<div>
			<h1 class="text-3xl font-bold text-base-content">Skema ID</h1>
			<p class="text-base-content opacity-70 mt-1">Kelola format ID pegawai yang digunakan di sistem</p>
		</div>
		<button class="btn btn-success text-white" on:click={openCreateModal}>
			<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
			</svg>
			Tambah Skema
		</button>
	</div>

	{#if loading}
		<div class="flex justify-center items-center min-h-[400px]">
			<span class="loading loading-spinner loading-lg text-primary"></span>
		</div>
	{:else}
		<!-- Schemes List -->
		<div class="card bg-base-100 shadow-lg">
			<div class="card-body">
				{#if schemes.length > 0}
					<div class="overflow-x-auto">
						<table class="table table-zebra">
							<thead>
								<tr>
									<th class="text-base-content">Kode</th>
									<th class="text-base-content">Label</th>
									<th class="text-base-content">Tipe Entity</th>
									<th class="text-base-content">Pattern</th>
									<th class="text-base-content">Panjang</th>
									<th class="text-base-content">Normalisasi</th>
									<th class="text-base-content">Contoh</th>
									<th class="text-base-content">Aksi</th>
								</tr>
							</thead>
							<tbody>
								{#each schemes as scheme}
									<tr>
										<td class="font-mono text-sm text-base-content">{scheme.code}</td>
										<td class="text-base-content font-medium">{scheme.label}</td>
										<td class="text-base-content opacity-70">{scheme.entity_type || '-'}</td>
										<td class="font-mono text-xs text-base-content opacity-70">
											{scheme.regex_pattern || '-'}
										</td>
										<td class="text-base-content opacity-70">
											{#if scheme.length_min && scheme.length_max}
												{scheme.length_min === scheme.length_max ? scheme.length_min : `${scheme.length_min}-${scheme.length_max}`}
											{:else if scheme.length_min}
												≥{scheme.length_min}
											{:else if scheme.length_max}
												≤{scheme.length_max}
											{:else}
												-
											{/if}
										</td>
										<td class="text-base-content opacity-70">{scheme.normalize_rule || 'NONE'}</td>
										<td class="font-mono text-sm text-base-content opacity-70">{scheme.example || '-'}</td>
										<td>
											<div class="flex gap-2">
												<button
													class="btn btn-sm btn-ghost"
													on:click={() => openEditModal(scheme)}
													aria-label="Edit skema"
												>
													<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
														<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
													</svg>
												</button>
												<button
													class="btn btn-sm btn-ghost text-error"
													on:click={() => openDeleteModal(scheme)}
													aria-label="Hapus skema"
												>
													<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
														<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
													</svg>
												</button>
											</div>
										</td>
									</tr>
								{/each}
							</tbody>
						</table>
					</div>
				{:else}
					<div class="text-center py-12">
						<p class="text-base-content opacity-50 mb-4">Belum ada skema ID</p>
						<button class="btn btn-success text-white" on:click={openCreateModal}>
							<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
							</svg>
							Tambah Skema Pertama
						</button>
					</div>
				{/if}
			</div>
		</div>
	{/if}
</div>

<!-- Modal Create/Edit -->
{#if showModal}
	<div class="modal modal-open">
		<div class="modal-box max-w-2xl">
			<div class="mb-6">
				<h3 class="text-2xl font-bold text-base-content mb-1">
					{editingScheme ? 'Edit Skema ID' : 'Tambah Skema ID Baru'}
				</h3>
				<p class="text-sm text-base-content opacity-60">Konfigurasi format ID pegawai yang akan digunakan di sistem</p>
			</div>

			<div class="space-y-5">
				<!-- Code -->
				<div class="form-control">
					<div class="label pb-1">
						<span class="label-text font-semibold text-base-content">Kode <span class="text-error">*</span></span>
					</div>
					<input
						type="text"
						class={`input input-bordered w-full text-base-content placeholder:text-base-content/50 ${formErrors.code ? 'input-error' : ''}`}
						placeholder="BANK_EMP_ID"
						bind:value={formData.code}
						on:input={handleCodeInput}
					/>
					{#if formErrors.code}
						<div class="label pt-1 pb-0">
							<span class="label-text-alt text-error">{formErrors.code}</span>
						</div>
					{:else if codeInputWarning}
						<div class="label pt-1 pb-0">
							<span class="label-text-alt text-warning">{codeInputWarning}</span>
						</div>
					{:else}
						<div class="label pt-1 pb-0">
							<span class="label-text-alt text-base-content opacity-50">Huruf besar, angka, dan underscore saja</span>
						</div>
					{/if}
				</div>

				<!-- Label -->
				<div class="form-control">
					<div class="label pb-1">
						<span class="label-text font-semibold text-base-content">Label <span class="text-error">*</span></span>
					</div>
					<input
						type="text"
						class={`input input-bordered w-full text-base-content placeholder:text-base-content/50 ${formErrors.label ? 'input-error' : ''}`}
						placeholder="ID Pegawai Bank"
						bind:value={formData.label}
					/>
					{#if formErrors.label}
						<div class="label pt-1 pb-0">
							<span class="label-text-alt text-error">{formErrors.label}</span>
						</div>
					{/if}
				</div>

				<!-- Prefix -->
				<div class="form-control">
					<div class="label pb-1">
						<span class="label-text font-semibold text-base-content">Prefix ID <span class="text-error">*</span></span>
					</div>
					<input
						type="text"
						class={`input input-bordered w-full text-base-content placeholder:text-base-content/50 ${formErrors.prefix ? 'input-error' : ''}`}
						placeholder="NTB"
						bind:value={formData.prefix}
						on:input={(e) => {
							const input = e.currentTarget as HTMLInputElement;
							formData.prefix = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
						}}
					/>
					{#if formErrors.prefix}
						<div class="label pt-1 pb-0">
							<span class="label-text-alt text-error">{formErrors.prefix}</span>
						</div>
					{:else}
						<div class="label pt-1 pb-0">
							<span class="label-text-alt text-base-content opacity-50">Prefix yang akan muncul READONLY saat input ID pegawai (contoh: NTB)</span>
						</div>
					{/if}
				</div>

				<!-- Format Type -->
				<div class="form-control">
					<div class="label pb-1">
						<span class="label-text font-semibold text-base-content">Format ID <span class="text-error">*</span></span>
					</div>
					<select class="select select-bordered w-full" bind:value={formData.format_type}>
						<option value="NUMERIC">Angka saja (0-9)</option>
						<option value="ALPHANUMERIC">Huruf dan Angka (A-Z, 0-9)</option>
					</select>
					<div class="label pt-1 pb-0">
						<span class="label-text-alt text-base-content opacity-50">
							{#if formData.format_type === 'NUMERIC'}
								Format: Hanya angka (contoh: 12345678)
							{:else}
								Format: Huruf dan angka (contoh: AB1234)
							{/if}
						</span>
					</div>
				</div>

				<!-- Length Min/Max -->
				<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
					<div class="form-control">
						<div class="label pb-1">
							<span class="label-text font-semibold text-base-content">Panjang Minimum <span class="text-error">*</span></span>
						</div>
						<input
							type="number"
							class={`input input-bordered w-full text-base-content placeholder:text-base-content/50 ${formErrors.length_min ? 'input-error' : ''}`}
							placeholder="8"
							bind:value={formData.length_min}
							min="1"
							required
						/>
						{#if formErrors.length_min}
							<div class="label pt-1 pb-0">
								<span class="label-text-alt text-error">{formErrors.length_min}</span>
							</div>
						{/if}
					</div>
					<div class="form-control">
						<div class="label pb-1">
							<span class="label-text font-semibold text-base-content">Panjang Maksimum <span class="text-error">*</span></span>
						</div>
						<input
							type="number"
							class={`input input-bordered w-full text-base-content placeholder:text-base-content/50 ${formErrors.length_max ? 'input-error' : ''}`}
							placeholder="8"
							bind:value={formData.length_max}
							min="1"
							required
						/>
						{#if formErrors.length_max}
							<div class="label pt-1 pb-0">
								<span class="label-text-alt text-error">{formErrors.length_max}</span>
							</div>
						{/if}
					</div>
				</div>

			</div>

			<div class="modal-action mt-8 pt-6 border-t border-base-300">
				<button class="btn btn-outline btn-neutral text-base-content" on:click={closeModal} disabled={saving}>
					Batal
				</button>
				<button class="btn btn-success text-white" on:click={saveScheme} disabled={saving}>
					{#if saving}
						<span class="loading loading-spinner loading-sm"></span>
						Menyimpan...
					{:else}
						Simpan
					{/if}
				</button>
			</div>
		</div>
		<form method="dialog" class="modal-backdrop" on:submit|preventDefault={closeModal}>
			<button>close</button>
		</form>
	</div>
{/if}

<!-- Delete Confirmation Modal -->
{#if showDeleteModal && schemeToDelete}
	<div class="modal modal-open">
		<div class="modal-box">
			<div class="flex items-center gap-4 mb-6">
				<div class="flex-shrink-0">
					<div class="w-12 h-12 rounded-full bg-error/20 flex items-center justify-center">
						<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-error" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
						</svg>
					</div>
				</div>
				<div class="flex-1">
					<h3 class="text-2xl font-bold text-base-content mb-1">Hapus Skema ID</h3>
					<p class="text-sm text-base-content opacity-70">Tindakan ini tidak dapat dibatalkan</p>
				</div>
			</div>

			<div class="bg-base-200 rounded-lg p-4 mb-6">
				<p class="text-base-content">
					Yakin ingin menghapus skema <span class="font-semibold text-error">"{schemeToDelete.label}"</span>?
				</p>
			</div>

			<div class="modal-action">
				<button class="btn btn-outline btn-neutral text-base-content" on:click={closeDeleteModal}>
					Batal
				</button>
				<button class="btn btn-error text-white" on:click={confirmDelete}>
					<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
					</svg>
					Hapus
				</button>
			</div>
		</div>
		<form method="dialog" class="modal-backdrop" on:submit|preventDefault={closeDeleteModal}>
			<button>close</button>
		</form>
	</div>
{/if}

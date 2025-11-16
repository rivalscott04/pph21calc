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

	// Form state
	let formData = {
		code: '',
		label: '',
		entity_type: '',
		regex_pattern: '',
		length_min: '',
		length_max: '',
		normalize_rule: 'NONE',
		example: '',
		checksum_type: 'NONE'
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

	const checksumTypes = [
		{ value: 'NONE', label: 'Tidak ada checksum' },
		{ value: 'LUHN', label: 'Luhn algorithm' },
		{ value: 'MOD_N', label: 'Mod N' }
	];

	function openCreateModal() {
		editingScheme = null;
		formData = {
			code: '',
			label: '',
			entity_type: '',
			regex_pattern: '',
			length_min: '',
			length_max: '',
			normalize_rule: 'NONE',
			example: '',
			checksum_type: 'NONE'
		};
		formErrors = {};
		codeInputWarning = '';
		showModal = true;
	}

	function openEditModal(scheme: IdentifierScheme) {
		editingScheme = scheme;
		formData = {
			code: scheme.code,
			label: scheme.label,
			entity_type: scheme.entity_type || '',
			regex_pattern: scheme.regex_pattern || '',
			length_min: scheme.length_min?.toString() || '',
			length_max: scheme.length_max?.toString() || '',
			normalize_rule: scheme.normalize_rule || 'NONE',
			example: scheme.example || '',
			checksum_type: scheme.checksum_type || 'NONE'
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
			entity_type: '',
			regex_pattern: '',
			length_min: '',
			length_max: '',
			normalize_rule: 'NONE',
			example: '',
			checksum_type: 'NONE'
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

		if (formData.regex_pattern && !isValidRegex(formData.regex_pattern)) {
			formErrors.regex_pattern = 'Pattern regex tidak valid';
		}

		if (formData.length_min && (!/^\d+$/.test(formData.length_min) || parseInt(formData.length_min) < 1)) {
			formErrors.length_min = 'Panjang minimum harus angka positif';
		}

		if (formData.length_max && (!/^\d+$/.test(formData.length_max) || parseInt(formData.length_max) < 1)) {
			formErrors.length_max = 'Panjang maksimum harus angka positif';
		}

		if (formData.length_min && formData.length_max) {
			const min = parseInt(formData.length_min);
			const max = parseInt(formData.length_max);
			if (min > max) {
				formErrors.length_max = 'Panjang maksimum harus >= panjang minimum';
			}
		}

		return Object.keys(formErrors).length === 0;
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
			toast.error('Mohon perbaiki error pada form');
			return;
		}

		saving = true;
		try {
			const user = get(auth);
			const tenantId = user?.tenant?.id;

		const schemeData = {
			code: formData.code.trim().toUpperCase(),
			label: formData.label.trim(),
			entity_type: formData.entity_type.trim() || null,
			regex_pattern: formData.regex_pattern.trim() || null,
			length_min: formData.length_min ? parseInt(formData.length_min) : null,
			length_max: formData.length_max ? parseInt(formData.length_max) : null,
			normalize_rule: formData.normalize_rule || null,
			example: formData.example.trim() || null,
			checksum_type: formData.checksum_type || null
		} as Omit<IdentifierScheme, 'id' | 'tenant_id'>;

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
			} else {
				toast.error(error.message || 'Gagal menyimpan skema ID');
			}
		} finally {
			saving = false;
		}
	}

	async function deleteScheme(scheme: IdentifierScheme) {
		if (!confirm(`Yakin ingin menghapus skema "${scheme.label}"?`)) {
			return;
		}

		try {
			const user = get(auth);
			const tenantId = user?.tenant?.id;
			await configApi.deleteIdentifierScheme(scheme.id, tenantId);
			toast.success('Skema ID berhasil dihapus');
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
													on:click={() => deleteScheme(scheme)}
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

				<!-- Entity Type -->
				<div class="form-control">
					<div class="label pb-1">
						<span class="label-text font-semibold text-base-content">Tipe Entity</span>
					</div>
					<input
						type="text"
						class="input input-bordered w-full text-base-content placeholder:text-base-content/50"
						placeholder="BANK, BUMN, KAMPUS, dll"
						bind:value={formData.entity_type}
					/>
					<div class="label pt-1 pb-0">
						<span class="label-text-alt text-base-content opacity-50">Opsional: untuk grouping schemes</span>
					</div>
				</div>

				<!-- Regex Pattern -->
				<div class="form-control">
					<div class="label pb-1">
						<span class="label-text font-semibold text-base-content">Pattern Regex</span>
					</div>
					<input
						type="text"
						class={`input input-bordered w-full font-mono text-sm text-base-content placeholder:text-base-content/50 ${formErrors.regex_pattern ? 'input-error' : ''}`}
						placeholder="^[0-9]{8}$"
						bind:value={formData.regex_pattern}
					/>
					{#if formErrors.regex_pattern}
						<div class="label pt-1 pb-0">
							<span class="label-text-alt text-error">{formErrors.regex_pattern}</span>
						</div>
					{:else}
						<div class="label pt-1 pb-0">
							<span class="label-text-alt text-base-content opacity-50">Contoh: ^[0-9]{8}$ untuk 8 digit angka</span>
						</div>
					{/if}
				</div>

				<!-- Length Min/Max -->
				<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
					<div class="form-control">
						<div class="label pb-1">
							<span class="label-text font-semibold text-base-content">Panjang Minimum</span>
						</div>
						<input
							type="number"
							class={`input input-bordered w-full text-base-content placeholder:text-base-content/50 ${formErrors.length_min ? 'input-error' : ''}`}
							placeholder="8"
							bind:value={formData.length_min}
							min="1"
						/>
						{#if formErrors.length_min}
							<div class="label pt-1 pb-0">
								<span class="label-text-alt text-error">{formErrors.length_min}</span>
							</div>
						{/if}
					</div>
					<div class="form-control">
						<div class="label pb-1">
							<span class="label-text font-semibold text-base-content">Panjang Maksimum</span>
						</div>
						<input
							type="number"
							class={`input input-bordered w-full text-base-content placeholder:text-base-content/50 ${formErrors.length_max ? 'input-error' : ''}`}
							placeholder="8"
							bind:value={formData.length_max}
							min="1"
						/>
						{#if formErrors.length_max}
							<div class="label pt-1 pb-0">
								<span class="label-text-alt text-error">{formErrors.length_max}</span>
							</div>
						{/if}
					</div>
				</div>

				<!-- Normalize Rule -->
				<div class="form-control">
					<div class="label pb-1">
						<span class="label-text font-semibold text-base-content">Aturan Normalisasi</span>
					</div>
					<select class="select select-bordered w-full text-base-content" bind:value={formData.normalize_rule}>
						{#each normalizeRules as rule}
							<option value={rule.value}>{rule.label}</option>
						{/each}
					</select>
				</div>

				<!-- Checksum Type -->
				<div class="form-control">
					<div class="label pb-1">
						<span class="label-text font-semibold text-base-content">Tipe Checksum</span>
					</div>
					<select class="select select-bordered w-full text-base-content" bind:value={formData.checksum_type}>
						{#each checksumTypes as type}
							<option value={type.value}>{type.label}</option>
						{/each}
					</select>
				</div>

				<!-- Example -->
				<div class="form-control">
					<div class="label pb-1">
						<span class="label-text font-semibold text-base-content">Contoh</span>
					</div>
					<input
						type="text"
						class="input input-bordered w-full font-mono text-sm text-base-content placeholder:text-base-content/50"
						placeholder="00123456"
						bind:value={formData.example}
					/>
					<div class="label pt-1 pb-0">
						<span class="label-text-alt text-base-content opacity-50">Contoh ID yang valid sesuai skema ini</span>
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

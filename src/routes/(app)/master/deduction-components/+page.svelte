<script lang="ts">
	import { onMount } from 'svelte';
	import { deductionComponentsApi, type DeductionComponent } from '$lib/api/deductionComponents.js';
	import { toast } from '$lib/stores/toast.js';

	type FormMode = 'create' | 'edit';

	let components: DeductionComponent[] = [];
	let pagination = {
		current_page: 1,
		last_page: 1,
		per_page: 50,
		total: 0
	};
	let search = '';
	let loading = true;
	let saving = false;
	let deleting = false;
	let filterType = '';
	let filterCalculationType = '';
	let filterActive: boolean | null = null;

	let showFormModal = false;
	let formMode: FormMode = 'create';
	let formData = {
		code: '',
		name: '',
		type: 'custom' as 'mandatory' | 'custom',
		calculation_type: 'manual' as 'auto' | 'manual' | 'percentage',
		is_tax_deductible: true,
		priority: 0,
		is_active: true,
		notes: ''
	};
	let formErrors: Record<string, string> = {};
	let editingComponent: DeductionComponent | null = null;
	let deletingComponent: DeductionComponent | null = null;

	const typeOptions = [
		{ value: 'mandatory', label: 'Wajib' },
		{ value: 'custom', label: 'Custom' }
	];

	const calculationTypeOptions = [
		{ value: 'auto', label: 'Otomatis' },
		{ value: 'manual', label: 'Manual' },
		{ value: 'percentage', label: 'Persentase' }
	];

	onMount(async () => {
		await loadComponents();
	});

	async function loadComponents(page = pagination.current_page) {
		try {
			loading = true;
			const response = await deductionComponentsApi.list({
				search: search || undefined,
				type: filterType || undefined,
				calculation_type: filterCalculationType || undefined,
				is_active: filterActive !== null ? filterActive : undefined,
				per_page: pagination.per_page,
				page: page
			});
			components = response.data;
			// Sort by priority
			components.sort((a, b) => (a.priority || 0) - (b.priority || 0));
			pagination = {
				current_page: response.current_page,
				last_page: response.last_page,
				per_page: response.per_page,
				total: response.total
			};
		} catch (error) {
			console.error('Failed to load deduction components:', error);
			toast.error('Gagal memuat daftar komponen deduction');
		} finally {
			loading = false;
		}
	}

	function handleSearch() {
		loadComponents(1);
	}

	function openCreateModal() {
		formMode = 'create';
		showFormModal = true;
		formData = {
			code: '',
			name: '',
			type: 'custom',
			calculation_type: 'manual',
			is_tax_deductible: true,
			priority: 0,
			is_active: true,
			notes: ''
		};
		formErrors = {};
		editingComponent = null;
	}

	async function openEditModal(component: DeductionComponent) {
		try {
			formMode = 'edit';
			showFormModal = true;
			formErrors = {};
			saving = true;
			const detail = await deductionComponentsApi.get(component.id);
			editingComponent = detail;
			formData = {
				code: detail.code,
				name: detail.name,
				type: detail.type,
				calculation_type: detail.calculation_type,
				is_tax_deductible: detail.is_tax_deductible,
				priority: detail.priority || 0,
				is_active: detail.is_active !== undefined ? detail.is_active : true,
				notes: detail.notes || ''
			};
		} catch (error) {
			console.error('Failed to load component detail:', error);
			toast.error('Gagal memuat detail komponen deduction');
			showFormModal = false;
		} finally {
			saving = false;
		}
	}

	function closeFormModal() {
		showFormModal = false;
		formErrors = {};
	}

	function validateForm(): boolean {
		formErrors = {};
		if (!formData.code.trim()) {
			formErrors.code = 'Kode wajib diisi';
		}
		if (!formData.name.trim()) {
			formErrors.name = 'Nama wajib diisi';
		}
		if (formData.code.length > 50) {
			formErrors.code = 'Kode maksimal 50 karakter';
		}
		return Object.keys(formErrors).length === 0;
	}

	async function saveComponent() {
		if (!validateForm()) {
			toast.error('Mohon perbaiki error pada form');
			return;
		}

		saving = true;
		try {
			if (formMode === 'create') {
				await deductionComponentsApi.create({
					code: formData.code.trim(),
					name: formData.name.trim(),
					type: formData.type,
					calculation_type: formData.calculation_type,
					is_tax_deductible: formData.is_tax_deductible,
					priority: formData.priority,
					is_active: formData.is_active,
					notes: formData.notes.trim() || undefined
				});
				toast.success('Komponen deduction berhasil ditambahkan');
			} else if (editingComponent) {
				await deductionComponentsApi.update(editingComponent.id, {
					code: formData.code.trim(),
					name: formData.name.trim(),
					type: formData.type,
					calculation_type: formData.calculation_type,
					is_tax_deductible: formData.is_tax_deductible,
					priority: formData.priority,
					is_active: formData.is_active,
					notes: formData.notes.trim() || undefined
				});
				toast.success('Komponen deduction berhasil diperbarui');
			}
			await loadComponents();
			closeFormModal();
		} catch (error: any) {
			console.error('Failed to save component:', error);
			if (error.errors) {
				formErrors = error.errors;
			} else {
				toast.error(error.message || 'Gagal menyimpan komponen deduction');
			}
		} finally {
			saving = false;
		}
	}

	async function confirmDelete(component: DeductionComponent) {
		deletingComponent = component;
	}

	function cancelDelete() {
		deletingComponent = null;
	}

	async function deleteComponent() {
		if (!deletingComponent) return;

		deleting = true;
		try {
			await deductionComponentsApi.delete(deletingComponent.id);
			toast.success('Komponen deduction berhasil dihapus');
			await loadComponents();
			cancelDelete();
		} catch (error: any) {
			console.error('Failed to delete component:', error);
			toast.error(error.message || 'Gagal menghapus komponen deduction');
		} finally {
			deleting = false;
		}
	}

	function getTypeLabel(value: string): string {
		return typeOptions.find(opt => opt.value === value)?.label || value;
	}

	function getCalculationTypeLabel(value: string): string {
		return calculationTypeOptions.find(opt => opt.value === value)?.label || value;
	}
</script>

<div class="space-y-6">
	<!-- Header -->
	<div class="flex justify-between items-center">
		<div>
			<h1 class="text-3xl font-bold text-base-content">Master Komponen Pengurang</h1>
			<p class="text-base-content opacity-70 mt-1">
				Kelola komponen pengurang (Iuran Pensiun, Zakat, Biaya Jabatan, dll)
			</p>
		</div>
		<button class="btn btn-brand text-white" on:click={openCreateModal}>
			<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
			</svg>
			Tambah Komponen
		</button>
	</div>

	<!-- Filters -->
	<div class="card bg-base-100 shadow-lg">
		<div class="card-body">
			<div class="flex flex-wrap gap-4 items-end">
				<div class="form-control flex-1 min-w-[200px]">
					<label class="label">
						<span class="label-text text-base-content">Cari</span>
					</label>
					<input 
						type="text" 
						placeholder="Cari kode atau nama..." 
						class="input input-bordered text-base-content"
						bind:value={search}
						on:keydown={(e) => e.key === 'Enter' && handleSearch()}
					/>
				</div>
				<div class="form-control min-w-[150px]">
					<label class="label">
						<span class="label-text text-base-content">Tipe</span>
					</label>
					<select class="select select-bordered text-base-content" bind:value={filterType}>
						<option value="">Semua</option>
						{#each typeOptions as opt}
							<option value={opt.value}>{opt.label}</option>
						{/each}
					</select>
				</div>
				<div class="form-control min-w-[150px]">
					<label class="label">
						<span class="label-text text-base-content">Perhitungan</span>
					</label>
					<select class="select select-bordered text-base-content" bind:value={filterCalculationType}>
						<option value="">Semua</option>
						{#each calculationTypeOptions as opt}
							<option value={opt.value}>{opt.label}</option>
						{/each}
					</select>
				</div>
				<div class="form-control min-w-[150px]">
					<label class="label">
						<span class="label-text text-base-content">Status</span>
					</label>
					<select class="select select-bordered text-base-content" bind:value={filterActive}>
						<option value={null}>Semua</option>
						<option value={true}>Aktif</option>
						<option value={false}>Non-Aktif</option>
					</select>
				</div>
				<button class="btn btn-neutral text-white" on:click={handleSearch}>
					<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
					</svg>
					Cari
				</button>
			</div>
		</div>
	</div>

	<!-- Table -->
	<div class="card bg-base-100 shadow-lg">
		<div class="card-body">
			{#if loading}
				<div class="flex justify-center items-center min-h-[200px]">
					<span class="loading loading-spinner loading-lg"></span>
				</div>
			{:else if components.length === 0}
				<div class="alert alert-info">
					<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
					</svg>
					<span>Tidak ada komponen. Klik "Tambah Komponen" untuk menambahkan.</span>
				</div>
			{:else}
				<div class="overflow-x-auto">
					<table class="table table-zebra">
						<thead>
							<tr>
								<th class="text-base-content">Kode</th>
								<th class="text-base-content">Nama</th>
								<th class="text-base-content">Tipe</th>
								<th class="text-base-content">Perhitungan</th>
								<th class="text-base-content">Tax Deductible</th>
								<th class="text-base-content">Prioritas</th>
								<th class="text-base-content">Status</th>
								<th class="text-base-content">Aksi</th>
							</tr>
						</thead>
						<tbody>
							{#each components as component}
								<tr>
									<td class="text-base-content font-mono">{component.code}</td>
									<td class="text-base-content">{component.name}</td>
									<td>
										{#if component.type === 'mandatory'}
											<span class="badge badge-error">{getTypeLabel(component.type)}</span>
										{:else}
											<span class="badge badge-neutral">{getTypeLabel(component.type)}</span>
										{/if}
									</td>
									<td>
										<span class="badge badge-info badge-sm">{getCalculationTypeLabel(component.calculation_type)}</span>
									</td>
									<td>
										{#if component.is_tax_deductible}
											<span class="badge badge-success badge-sm">Ya</span>
										{:else}
											<span class="badge badge-error badge-sm">Tidak</span>
										{/if}
									</td>
									<td class="text-base-content">{component.priority || 0}</td>
									<td>
										{#if component.is_active !== false}
											<span class="badge badge-success badge-sm">Aktif</span>
										{:else}
											<span class="badge badge-error badge-sm">Non-Aktif</span>
										{/if}
									</td>
									<td>
										<div class="flex gap-2">
											<button 
												class="btn btn-sm btn-ghost"
												on:click={() => openEditModal(component)}
											>
												<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
													<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
												</svg>
												Edit
											</button>
											{#if component.type === 'custom'}
												<button 
													class="btn btn-sm btn-ghost text-error"
													on:click={() => confirmDelete(component)}
												>
													<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
														<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
													</svg>
													Hapus
												</button>
											{/if}
										</div>
									</td>
								</tr>
							{/each}
						</tbody>
					</table>
				</div>

				<!-- Pagination -->
				{#if pagination.last_page > 1}
					<div class="flex justify-center items-center gap-2 mt-4">
						<button 
							class="btn btn-sm btn-ghost"
							disabled={pagination.current_page === 1}
							on:click={() => loadComponents(pagination.current_page - 1)}
						>
							Sebelumnya
						</button>
						<span class="text-base-content">
							Halaman {pagination.current_page} dari {pagination.last_page}
						</span>
						<button 
							class="btn btn-sm btn-ghost"
							disabled={pagination.current_page === pagination.last_page}
							on:click={() => loadComponents(pagination.current_page + 1)}
						>
							Selanjutnya
						</button>
					</div>
				{/if}
			{/if}
		</div>
	</div>
</div>

<!-- Form Modal -->
{#if showFormModal}
	<div class="modal modal-open">
		<div class="modal-box max-w-3xl max-h-[90vh] overflow-y-auto">
			<h3 class="font-bold text-xl text-base-content mb-6">
				{formMode === 'create' ? 'Tambah Komponen Pengurang' : 'Edit Komponen Pengurang'}
			</h3>

			<div class="space-y-6">
				<!-- Informasi Dasar -->
				<div class="space-y-4">
					<h4 class="font-semibold text-base-content text-lg border-b border-base-300 pb-2">
						Informasi Dasar
					</h4>
					
					<!-- Code -->
					<div class="form-control">
						<label class="label">
							<span class="label-text text-base-content font-medium">
								Kode <span class="text-error">*</span>
								<div class="tooltip tooltip-right" data-tip="Kode unik untuk identifikasi komponen. Harus unik per tenant. Contoh: IP001, ZAKAT, BJ001">
									<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline ml-1 text-base-content opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
									</svg>
								</div>
							</span>
						</label>
						<input 
							type="text" 
							placeholder="Contoh: IP001, ZAKAT, BJ001" 
							class="input input-bordered text-base-content {formErrors.code ? 'input-error' : ''}"
							bind:value={formData.code}
						/>
						{#if formErrors.code}
							<label class="label">
								<span class="label-text-alt text-error">{formErrors.code}</span>
							</label>
						{:else}
							<label class="label">
								<span class="label-text-alt text-base-content opacity-70">Maksimal 50 karakter, harus unik</span>
							</label>
						{/if}
					</div>

					<!-- Name -->
					<div class="form-control">
						<label class="label">
							<span class="label-text text-base-content font-medium">
								Nama <span class="text-error">*</span>
								<div class="tooltip tooltip-right" data-tip="Nama komponen yang akan ditampilkan di form payroll. Contoh: Iuran Pensiun, Zakat, Biaya Jabatan">
									<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline ml-1 text-base-content opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
									</svg>
								</div>
							</span>
						</label>
						<input 
							type="text" 
							placeholder="Contoh: Iuran Pensiun, Zakat, Biaya Jabatan" 
							class="input input-bordered text-base-content {formErrors.name ? 'input-error' : ''}"
							bind:value={formData.name}
						/>
						{#if formErrors.name}
							<label class="label">
								<span class="label-text-alt text-error">{formErrors.name}</span>
							</label>
						{/if}
					</div>

					<!-- Type & Calculation Type -->
					<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
						<div class="form-control">
							<label class="label">
								<span class="label-text text-base-content font-medium">
									Tipe <span class="text-error">*</span>
									<div class="tooltip tooltip-right" data-tip="Wajib: komponen yang harus ada sesuai peraturan PPH21 (iuran pensiun, zakat). Custom: komponen tambahan yang bisa berbeda per tenant">
										<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline ml-1 text-base-content opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
										</svg>
									</div>
								</span>
							</label>
							<select 
								class="select select-bordered text-base-content {formErrors.type ? 'select-error' : ''}"
								bind:value={formData.type}
							>
								{#each typeOptions as opt}
									<option value={opt.value}>{opt.label}</option>
								{/each}
							</select>
							<label class="label">
								<span class="label-text-alt text-base-content opacity-70">
									{#if formData.type === 'mandatory'}
										Komponen wajib sesuai peraturan PPH21
									{:else}
										Komponen tambahan yang bisa dikustomisasi
									{/if}
								</span>
							</label>
						</div>

						<div class="form-control">
							<label class="label">
								<span class="label-text text-base-content font-medium">
									Tipe Perhitungan <span class="text-error">*</span>
									<div class="tooltip tooltip-right" data-tip="Auto: dihitung otomatis oleh sistem (contoh: Biaya Jabatan 5% bruto). Manual: user input manual. Percentage: perhitungan persentase">
										<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline ml-1 text-base-content opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
										</svg>
									</div>
								</span>
							</label>
							<select 
								class="select select-bordered text-base-content {formErrors.calculation_type ? 'select-error' : ''}"
								bind:value={formData.calculation_type}
							>
								{#each calculationTypeOptions as opt}
									<option value={opt.value}>{opt.label}</option>
								{/each}
							</select>
							<label class="label">
								<span class="label-text-alt text-base-content opacity-70">
									{#if formData.calculation_type === 'auto'}
										Dihitung otomatis oleh sistem
									{:else if formData.calculation_type === 'manual'}
										User input manual di form payroll
									{:else}
										Perhitungan berdasarkan persentase
									{/if}
								</span>
							</label>
						</div>
					</div>
				</div>

				<!-- Pengaturan Pajak -->
				<div class="space-y-4">
					<h4 class="font-semibold text-base-content text-lg border-b border-base-300 pb-2">
						Pengaturan Pajak
					</h4>
					
					<div class="form-control">
						<label class="label">
							<span class="label-text text-base-content font-medium">
								Tax Deductible
								<div class="tooltip tooltip-right" data-tip="Jika aktif, komponen ini dapat dikurangkan dari bruto untuk menghitung neto. Hanya komponen yang tax-deductible yang mengurangi penghasilan kena pajak">
									<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline ml-1 text-base-content opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
									</svg>
								</div>
							</span>
						</label>
						<div class="card bg-base-200 p-4">
							<label class="cursor-pointer label justify-start gap-4">
								<input 
									type="checkbox" 
									class="toggle toggle-primary"
									bind:checked={formData.is_tax_deductible}
								/>
								<div>
									<span class="label-text text-base-content font-medium">
										Dapat dikurangkan dari PPh21
									</span>
									<p class="text-sm text-base-content opacity-70 mt-1">
										{#if formData.is_tax_deductible}
											Komponen ini mengurangi penghasilan kena pajak (neto = bruto - deduction)
										{:else}
											Komponen ini tidak mengurangi penghasilan kena pajak
										{/if}
									</p>
								</div>
							</label>
						</div>
					</div>
				</div>

				<!-- Pengaturan Tampilan -->
				<div class="space-y-4">
					<h4 class="font-semibold text-base-content text-lg border-b border-base-300 pb-2">
						Pengaturan Tampilan
					</h4>
					
					<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
						<!-- Prioritas -->
						<div class="form-control">
							<label class="label">
								<span class="label-text text-base-content font-medium">
									Prioritas
									<div class="tooltip tooltip-left" data-tip="Urutan tampil: angka kecil = tampil dulu. 0 = pertama.">
										<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline ml-1 text-base-content opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
										</svg>
									</div>
								</span>
							</label>
							<input 
								type="number" 
								min="0"
								class="input input-bordered text-base-content"
								bind:value={formData.priority}
							/>
						</div>

						<!-- Status Aktif -->
						<div class="form-control">
							<label class="label">
								<span class="label-text text-base-content font-medium">
									Status Aktif
									<div class="tooltip tooltip-left" data-tip="Non-aktif: komponen tidak muncul di form payroll. Data tetap tersimpan.">
										<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline ml-1 text-base-content opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
										</svg>
									</div>
								</span>
							</label>
							<div class="card bg-base-200 p-4">
								<label class="cursor-pointer label justify-start gap-4">
									<input 
										type="checkbox" 
										class="toggle toggle-primary"
										bind:checked={formData.is_active}
									/>
									<span class="label-text text-base-content font-medium">
										Aktif
									</span>
								</label>
							</div>
						</div>
					</div>
				</div>

				<!-- Catatan -->
				<div class="space-y-4">
					<h4 class="font-semibold text-base-content text-lg border-b border-base-300 pb-2">
						Catatan Tambahan
					</h4>
					
					<div class="form-control">
						<label class="label">
							<span class="label-text text-base-content font-medium">
								Catatan
								<div class="tooltip tooltip-right" data-tip="Catatan internal untuk dokumentasi atau penjelasan tentang komponen ini. Contoh: Maksimal 5% dari bruto atau 200rb/bulan">
									<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline ml-1 text-base-content opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
									</svg>
								</div>
							</span>
						</label>
						<textarea 
							class="textarea textarea-bordered text-base-content"
							placeholder="Catatan tambahan (opsional). Contoh: Maksimal 5% dari bruto atau 200rb/bulan"
							bind:value={formData.notes}
							rows="3"
						></textarea>
					</div>
				</div>
			</div>

			<div class="modal-action mt-6">
				<button class="btn btn-ghost" on:click={closeFormModal} disabled={saving}>
					Batal
				</button>
				<button class="btn btn-primary text-white" on:click={saveComponent} disabled={saving}>
					{#if saving}
						<span class="loading loading-spinner loading-sm"></span>
						Menyimpan...
					{:else}
						<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
						</svg>
						Simpan
					{/if}
				</button>
			</div>
		</div>
		<div class="modal-backdrop" on:click={closeFormModal}></div>
	</div>
{/if}

<!-- Delete Confirmation Modal -->
{#if deletingComponent}
	<div class="modal modal-open">
		<div class="modal-box">
			<h3 class="font-bold text-lg text-base-content">Konfirmasi Hapus</h3>
			<p class="py-4 text-base-content">
				Apakah Anda yakin ingin menghapus komponen <strong>{deletingComponent.name}</strong>?
				<br />
				<span class="text-error">Tindakan ini tidak dapat dibatalkan.</span>
			</p>
			<div class="modal-action">
				<button class="btn btn-ghost" on:click={cancelDelete} disabled={deleting}>
					Batal
				</button>
				<button class="btn btn-error text-white" on:click={deleteComponent} disabled={deleting}>
					{#if deleting}
						<span class="loading loading-spinner loading-sm"></span>
						Menghapus...
					{:else}
						Hapus
					{/if}
				</button>
			</div>
		</div>
		<div class="modal-backdrop" on:click={cancelDelete}></div>
	</div>
{/if}


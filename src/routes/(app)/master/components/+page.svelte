<script lang="ts">
	import { onMount } from 'svelte';
	import { componentsApi, type Component } from '$lib/api/components.js';
	import { toast } from '$lib/stores/toast.js';

	type FormMode = 'create' | 'edit';

	let components: Component[] = [];
	let pagination = {
		current_page: 1,
		last_page: 1,
		per_page: 50,
		total: 0
	};
	let search = '';
	let loading = true;
	let saving = false;
	let filterGroup = '';
	let filterTaxable: boolean | null = null;

const filterIds = {
	search: 'component-filter-search',
	group: 'component-filter-group',
	taxable: 'component-filter-taxable'
} as const;

const formFieldIds = {
	code: 'component-form-code',
	name: 'component-form-name',
	group: 'component-form-group',
	taxableToggle: 'component-form-taxable',
	mandatoryToggle: 'component-form-mandatory',
	priority: 'component-form-priority',
	activeToggle: 'component-form-active',
	notes: 'component-form-notes'
} as const;

	let showFormModal = false;
	let formMode: FormMode = 'create';
	let formData = {
		code: '',
		name: '',
		group: 'gaji_pokok' as 'gaji_pokok' | 'tunjangan' | 'bonus' | 'lembur' | 'natura' | 'lainnya',
		taxable: true,
		is_mandatory: false,
		priority: 0,
		is_active: true,
		notes: ''
	};
	let formErrors: Record<string, string> = {};
	let editingComponent: Component | null = null;
const tableSkeletonRows = Array.from({ length: 6 });
const tableSkeletonCols = Array.from({ length: 8 });

	const groupOptions = [
		{ value: 'gaji_pokok', label: 'Gaji Pokok' },
		{ value: 'tunjangan', label: 'Tunjangan' },
		{ value: 'bonus', label: 'Bonus' },
		{ value: 'lembur', label: 'Lembur' },
		{ value: 'natura', label: 'Natura' },
		{ value: 'lainnya', label: 'Lainnya' }
	];

	onMount(async () => {
		await loadComponents();
	});

	async function loadComponents(page = pagination.current_page) {
		try {
			loading = true;
			const response = await componentsApi.list({
				search: search || undefined,
				group: filterGroup || undefined,
				taxable: filterTaxable !== null ? filterTaxable : undefined,
				per_page: pagination.per_page,
				page
			});
			components = response.data;
			pagination = {
				current_page: response.current_page,
				last_page: response.last_page,
				per_page: response.per_page,
				total: response.total
			};
		} catch (error) {
			console.error('Failed to load components:', error);
			toast.error('Gagal memuat daftar komponen');
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
			group: 'gaji_pokok',
			taxable: true,
			is_mandatory: false,
			priority: 0,
			is_active: true,
			notes: ''
		};
		formErrors = {};
		editingComponent = null;
	}

	async function openEditModal(component: Component) {
		try {
			formMode = 'edit';
			showFormModal = true;
			formErrors = {};
			saving = true;
			const detail = await componentsApi.get(component.id);
			editingComponent = detail;
			formData = {
				code: detail.code,
				name: detail.name,
				group: detail.group,
				taxable: detail.taxable,
				is_mandatory: detail.is_mandatory || false,
				priority: detail.priority || 0,
				is_active: detail.is_active !== undefined ? detail.is_active : true,
				notes: detail.notes || ''
			};
		} catch (error) {
			console.error('Failed to load component detail:', error);
			toast.error('Gagal memuat detail komponen');
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
				await componentsApi.create({
					code: formData.code.trim(),
					name: formData.name.trim(),
					group: formData.group,
					taxable: formData.taxable,
					is_mandatory: formData.is_mandatory,
					priority: formData.priority,
					is_active: formData.is_active,
					notes: formData.notes.trim() || undefined
				});
				toast.success('Komponen berhasil ditambahkan');
			} else if (editingComponent) {
				await componentsApi.update(editingComponent.id, {
					code: formData.code.trim(),
					name: formData.name.trim(),
					group: formData.group,
					taxable: formData.taxable,
					is_mandatory: formData.is_mandatory,
					priority: formData.priority,
					is_active: formData.is_active,
					notes: formData.notes.trim() || undefined
				});
				toast.success('Komponen berhasil diperbarui');
			}
			await loadComponents();
			closeFormModal();
		} catch (error: any) {
			console.error('Failed to save component:', error);
			if (error.errors) {
				formErrors = error.errors;
			} else {
				toast.error(error.message || 'Gagal menyimpan komponen');
			}
		} finally {
			saving = false;
		}
	}

	function getGroupLabel(value: string): string {
		return groupOptions.find(opt => opt.value === value)?.label || value;
	}
</script>

<div class="space-y-6">
	<!-- Header -->
	<div class="flex justify-between items-center">
		<div>
			<h1 class="text-3xl font-bold text-base-content">Master Komponen Penghasilan</h1>
			<p class="text-base-content opacity-70 mt-1">
				Kelola komponen penghasilan (Gaji Pokok, Tunjangan, Bonus, Lembur, Natura, dll)
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
					<label class="label" for={filterIds.search}>
						<span class="label-text text-base-content">Cari</span>
					</label>
					<input 
						id={filterIds.search}
						type="text" 
						placeholder="Cari kode atau nama..." 
						class="input input-bordered text-base-content"
						bind:value={search}
						on:keydown={(e) => e.key === 'Enter' && handleSearch()}
					/>
				</div>
				<div class="form-control min-w-[150px]">
					<label class="label" for={filterIds.group}>
						<span class="label-text text-base-content">Grup</span>
					</label>
					<select id={filterIds.group} class="select select-bordered text-base-content" bind:value={filterGroup}>
						<option value="">Semua</option>
						{#each groupOptions as opt}
							<option value={opt.value}>{opt.label}</option>
						{/each}
					</select>
				</div>
				<div class="form-control min-w-[150px]">
					<label class="label" for={filterIds.taxable}>
						<span class="label-text text-base-content">Taxable</span>
					</label>
					<select id={filterIds.taxable} class="select select-bordered text-base-content" bind:value={filterTaxable}>
						<option value={null}>Semua</option>
						<option value={true}>Taxable</option>
						<option value={false}>Non-Taxable</option>
					</select>
				</div>
				<button class="btn btn-brand text-white" on:click={handleSearch}>
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
				<div class="space-y-4">
					<div class="grid grid-cols-8 gap-4">
						{#each tableSkeletonCols as _, colIndex}
							<div class="skeleton h-4 w-full {colIndex === 0 ? 'col-span-2' : ''}"></div>
						{/each}
					</div>
					{#each tableSkeletonRows as _, rowIndex}
						<div class="grid grid-cols-8 gap-4">
							{#each tableSkeletonCols as __}
								<div class="skeleton h-5 w-full"></div>
							{/each}
						</div>
						<div class="divider my-2"></div>
					{/each}
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
								<th class="text-base-content">Grup</th>
								<th class="text-base-content">Taxable</th>
								<th class="text-base-content">Wajib</th>
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
										<span class="badge badge-neutral">{getGroupLabel(component.group)}</span>
									</td>
									<td>
										{#if component.taxable}
											<span class="badge badge-success">Taxable</span>
										{:else}
											<span class="badge badge-error">Non-Taxable</span>
										{/if}
									</td>
									<td>
										{#if component.is_mandatory}
											<span class="badge badge-error badge-sm">Wajib</span>
										{:else}
											<span class="badge badge-ghost badge-sm">Opsional</span>
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
										<button 
											class="btn btn-sm btn-ghost"
											on:click={() => openEditModal(component)}
										>
											<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
											</svg>
											Edit
										</button>
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
				{formMode === 'create' ? 'Tambah Komponen Penghasilan' : 'Edit Komponen Penghasilan'}
			</h3>

			<div class="space-y-6">
				<!-- Informasi Dasar -->
				<div class="space-y-4">
					<h4 class="font-semibold text-base-content text-lg border-b border-base-300 pb-2">
						Informasi Dasar
					</h4>
					
					<!-- Code -->
					<div class="form-control">
						<label class="label" for={formFieldIds.code}>
							<span class="label-text text-base-content font-medium">
								Kode <span class="text-error">*</span>
								<div class="tooltip tooltip-right" data-tip="Kode unik untuk identifikasi komponen. Harus unik per tenant. Contoh: GP001, TUNJ001">
									<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline ml-1 text-base-content opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
									</svg>
								</div>
							</span>
						</label>
						<input 
							id={formFieldIds.code}
							type="text" 
							placeholder="Contoh: GP001, TUNJ001" 
							class="input input-bordered text-base-content {formErrors.code ? 'input-error' : ''}"
							bind:value={formData.code}
						/>
						{#if formErrors.code}
							<div class="label">
								<span class="label-text-alt text-error">{formErrors.code}</span>
							</div>
						{:else}
							<div class="label">
								<span class="label-text-alt text-base-content opacity-70">Maksimal 50 karakter, harus unik</span>
							</div>
						{/if}
					</div>

					<!-- Name -->
					<div class="form-control">
						<label class="label" for={formFieldIds.name}>
							<span class="label-text text-base-content font-medium">
								Nama <span class="text-error">*</span>
								<div class="tooltip tooltip-right" data-tip="Nama komponen yang akan ditampilkan di form payroll. Contoh: Gaji Pokok, Tunjangan Transport">
									<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline ml-1 text-base-content opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
									</svg>
								</div>
							</span>
						</label>
						<input 
							id={formFieldIds.name}
							type="text" 
							placeholder="Contoh: Gaji Pokok, Tunjangan Transport" 
							class="input input-bordered text-base-content {formErrors.name ? 'input-error' : ''}"
							bind:value={formData.name}
						/>
						{#if formErrors.name}
							<div class="label">
								<span class="label-text-alt text-error">{formErrors.name}</span>
							</div>
						{/if}
					</div>

					<!-- Group -->
					<div class="form-control">
						<label class="label" for={formFieldIds.group}>
							<span class="label-text text-base-content font-medium">
								Grup <span class="text-error">*</span>
								<div class="tooltip tooltip-right" data-tip="Kategori komponen sesuai peraturan PPH21. Gaji Pokok wajib ada, lainnya opsional">
									<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline ml-1 text-base-content opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
									</svg>
								</div>
							</span>
						</label>
						<select 
							id={formFieldIds.group}
							class="select select-bordered text-base-content {formErrors.group ? 'select-error' : ''}"
							bind:value={formData.group}
						>
							{#each groupOptions as opt}
								<option value={opt.value}>{opt.label}</option>
							{/each}
						</select>
						<div class="label">
							<span class="label-text-alt text-base-content opacity-70">
								Pilih kategori sesuai jenis penghasilan
							</span>
						</div>
					</div>
				</div>

				<!-- Pengaturan Pajak -->
				<div class="space-y-4">
					<h4 class="font-semibold text-base-content text-lg border-b border-base-300 pb-2">
						Pengaturan Pajak
					</h4>
					
					<div class="form-control">
						<label class="label" for={formFieldIds.taxableToggle}>
							<span class="label-text text-base-content font-medium">
								Taxable
								<div class="tooltip tooltip-right" data-tip="Jika aktif, komponen ini akan dihitung dalam bruto untuk perhitungan PPh21. Non-taxable tidak masuk perhitungan pajak">
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
									class="toggle toggle-brand"
									id={formFieldIds.taxableToggle}
									bind:checked={formData.taxable}
								/>
								<div>
									<span class="label-text text-base-content font-medium">
										Termasuk perhitungan PPh21
									</span>
									<p class="text-sm text-base-content opacity-70 mt-1">
										{#if formData.taxable}
											Komponen ini akan ditambahkan ke bruto untuk perhitungan PPh21
										{:else}
											Komponen ini tidak akan dihitung dalam perhitungan PPh21
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
					
					<div class="grid grid-cols-1 md:grid-cols-3 gap-4" id="component-display-settings">
						<!-- Wajib -->
						<div class="form-control">
							<label class="label" for={formFieldIds.mandatoryToggle}>
								<span class="label-text text-base-content font-medium">
									Komponen Wajib
									<div class="tooltip tooltip-right" data-tip="Aktif: komponen wajib diisi saat input payroll. Sistem akan validasi.">
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
										class="toggle toggle-brand"
										id={formFieldIds.mandatoryToggle}
										bind:checked={formData.is_mandatory}
									/>
									<span class="label-text text-base-content font-medium">
										Wajib diisi
									</span>
								</label>
							</div>
						</div>

						<!-- Prioritas -->
						<div class="form-control">
							<label class="label" for={formFieldIds.priority}>
								<span class="label-text text-base-content font-medium">
									Prioritas
									<div class="tooltip tooltip-right" data-tip="Urutan tampil: angka kecil = tampil dulu. 0 = pertama.">
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
								id={formFieldIds.priority}
								bind:value={formData.priority}
							/>
						</div>

						<!-- Status Aktif -->
						<div class="form-control">
							<label class="label" for={formFieldIds.activeToggle}>
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
										class="toggle toggle-brand"
										id={formFieldIds.activeToggle}
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
						<label class="label" for={formFieldIds.notes}>
							<span class="label-text text-base-content font-medium">
								Catatan
								<div class="tooltip tooltip-right" data-tip="Catatan internal untuk dokumentasi atau penjelasan tentang komponen ini">
									<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline ml-1 text-base-content opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
									</svg>
								</div>
							</span>
						</label>
						<textarea 
							id={formFieldIds.notes}
							class="textarea textarea-bordered text-base-content"
							placeholder="Catatan tambahan (opsional). Contoh: Komponen ini untuk pegawai tetap saja"
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
				<button class="btn btn-brand text-white" on:click={saveComponent} disabled={saving}>
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
		<button
			type="button"
			class="modal-backdrop"
			on:click={closeFormModal}
			on:keydown={(event) => {
				if (event.key === 'Enter' || event.key === ' ') {
					event.preventDefault();
					closeFormModal();
				}
			}}
			aria-label="Tutup modal"
		></button>
	</div>
{/if}


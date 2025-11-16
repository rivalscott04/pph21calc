<script lang="ts">
	import { onMount } from 'svelte';
	import { personsApi, type Person, type PersonIdentifier } from '$lib/api/persons.js';
	import { configApi, type IdentifierScheme } from '$lib/api/config.js';
	import { toast } from '$lib/stores/toast.js';

	type FormMode = 'create' | 'edit';

	let persons: Person[] = [];
	let pagination = {
		current_page: 1,
		last_page: 1,
		per_page: 10,
		total: 0
	};
	let search = '';
	let loading = true;
	let saving = false;

	let showFormModal = false;
	let formMode: FormMode = 'create';
	let formData = {
		full_name: '',
		nik: '',
		npwp: '',
		birth_date: ''
	};
	let formErrors: Record<string, string> = {};
	let editingPerson: Person | null = null;

	// Identifier management
	let identifierSchemes: IdentifierScheme[] = [];
	let identifierList: PersonIdentifier[] = [];
	let identifierForm = {
		scheme_id: '',
		raw_value: ''
	};
	let identifierErrors: Record<string, string> = {};
	let identifierSaving = false;

	const perPageOptions = [10, 25, 50];

	onMount(async () => {
		await Promise.all([loadPersons(), loadIdentifierSchemes()]);
	});

	async function loadPersons(page = pagination.current_page) {
		try {
			loading = true;
			const response = await personsApi.list({
				search: search || undefined,
				per_page: pagination.per_page,
				page
			});
			persons = response.data;
			pagination = {
				current_page: response.current_page,
				last_page: response.last_page,
				per_page: response.per_page,
				total: response.total
			};
		} catch (error) {
			console.error('Failed to load persons:', error);
			toast.error('Gagal memuat daftar pegawai');
		} finally {
			loading = false;
		}
	}

	async function loadIdentifierSchemes() {
		try {
			identifierSchemes = await configApi.getIdentifierSchemes();
		} catch (error) {
			console.error('Failed to load identifier schemes:', error);
			toast.error('Gagal memuat skema ID');
		}
	}

	function handleSearch() {
		loadPersons(1);
	}

	function changePerPage(value: number) {
		pagination.per_page = value;
		loadPersons(1);
	}

	function openCreateModal() {
		formMode = 'create';
		showFormModal = true;
		formData = {
			full_name: '',
			nik: '',
			npwp: '',
			birth_date: ''
		};
		formErrors = {};
		identifierList = [];
		editingPerson = null;
	}

	async function openEditModal(person: Person) {
		try {
			formMode = 'edit';
			showFormModal = true;
			formErrors = {};
			saving = true;
			const detail = await personsApi.get(person.id);
			editingPerson = detail;
			formData = {
				full_name: detail.full_name,
				nik: detail.nik || '',
				npwp: detail.npwp || '',
				birth_date: detail.birth_date || ''
			};
			identifierList = detail.identifiers ?? [];
		} catch (error) {
			console.error('Failed to load person detail:', error);
			toast.error('Gagal memuat detail pegawai');
			showFormModal = false;
		} finally {
			saving = false;
		}
	}

	function closeFormModal() {
		showFormModal = false;
		formErrors = {};
		identifierErrors = {};
		identifierForm = { scheme_id: '', raw_value: '' };
	}

	function validateForm(): boolean {
		formErrors = {};
		if (!formData.full_name.trim()) {
			formErrors.full_name = 'Nama lengkap wajib diisi';
		}
		if (formData.nik && formData.nik.length > 16) {
			formErrors.nik = 'NIK maksimal 16 karakter';
		}
		if (formData.npwp && formData.npwp.length > 20) {
			formErrors.npwp = 'NPWP maksimal 20 karakter';
		}
		return Object.keys(formErrors).length === 0;
	}

	async function savePerson() {
		if (!validateForm()) {
			toast.error('Mohon perbaiki error pada form');
			return;
		}

		saving = true;
		try {
			if (formMode === 'create') {
				await personsApi.create({
					full_name: formData.full_name.trim(),
					nik: formData.nik?.trim() || null,
					npwp: formData.npwp?.trim() || null,
					birth_date: formData.birth_date || null
				});
				toast.success('Pegawai berhasil ditambahkan');
			} else if (editingPerson) {
				await personsApi.update(editingPerson.id, {
					full_name: formData.full_name.trim(),
					nik: formData.nik?.trim() || null,
					npwp: formData.npwp?.trim() || null,
					birth_date: formData.birth_date || null
				});
				toast.success('Data pegawai berhasil diperbarui');
			}
			await loadPersons();
			closeFormModal();
		} catch (error: any) {
			console.error('Failed to save person:', error);
			if (error.errors) {
				formErrors = error.errors;
			} else {
				toast.error(error.message || 'Gagal menyimpan pegawai');
			}
		} finally {
			saving = false;
		}
	}

	function normalizeIdentifier(value: string, scheme?: IdentifierScheme | null): string {
		if (!scheme) return value.trim();
		const trimmed = value.trim();
		switch (scheme.normalize_rule) {
			case 'NUMERIC':
				return trimmed.replace(/[^0-9]/g, '');
			case 'ALNUM':
				return trimmed.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
			case 'UPPER':
				return trimmed.toUpperCase();
			default:
				return trimmed;
		}
	}

	$: selectedScheme = identifierSchemes.find((scheme) => scheme.id === Number(identifierForm.scheme_id)) || null;
	$: normalizedPreview = normalizeIdentifier(identifierForm.raw_value, selectedScheme);

	function validateIdentifierForm(): boolean {
		identifierErrors = {};
		if (!identifierForm.scheme_id) {
			identifierErrors.scheme_id = 'Pilih skema ID';
			return false;
		}
		if (!identifierForm.raw_value.trim()) {
			identifierErrors.raw_value = 'Nilai ID wajib diisi';
			return false;
		}
		const scheme = selectedScheme;
		if (!scheme) {
			identifierErrors.scheme_id = 'Skema tidak valid';
			return false;
		}

		const normalized = normalizedPreview;
		if (scheme.length_min && normalized.length < scheme.length_min) {
			identifierErrors.raw_value = `Minimal ${scheme.length_min} karakter`;
		}
		if (scheme.length_max && normalized.length > scheme.length_max) {
			identifierErrors.raw_value = `Maksimal ${scheme.length_max} karakter`;
		}
		if (scheme.regex_pattern) {
			try {
				const regex = new RegExp(scheme.regex_pattern);
				if (!regex.test(identifierForm.raw_value.trim())) {
					identifierErrors.raw_value = 'Nilai tidak sesuai pola yang ditentukan';
				}
			} catch (error) {
				console.warn('Invalid regex pattern on scheme', scheme.code, error);
			}
		}

		return Object.keys(identifierErrors).length === 0;
	}

	async function addIdentifier() {
		if (!editingPerson) {
			toast.error('Simpan data pegawai terlebih dahulu');
			return;
		}
		if (!validateIdentifierForm()) {
			toast.error('Periksa input identifier');
			return;
		}

		const schemeId = Number(identifierForm.scheme_id);
		const normalized = normalizedPreview;

		identifierSaving = true;
		try {
			const uniqueResult = await personsApi.checkUnique(schemeId, normalized);
			if (!uniqueResult.is_unique) {
				identifierErrors.raw_value = 'ID ini sudah digunakan';
				identifierSaving = false;
				return;
			}

			await personsApi.addIdentifier(editingPerson.id, {
				scheme_id: schemeId,
				raw_value: identifierForm.raw_value.trim()
			});

			const updated = await personsApi.get(editingPerson.id);
			editingPerson = updated;
			identifierList = updated.identifiers ?? [];
			persons = persons.map((person) => (person.id === updated.id ? updated : person));

			toast.success('Identifier berhasil ditambahkan');
			identifierForm = { scheme_id: '', raw_value: '' };
			identifierErrors = {};
		} catch (error: any) {
			console.error('Failed to add identifier:', error);
			if (error.errors) {
				identifierErrors = error.errors;
			} else {
				toast.error(error.message || 'Gagal menambahkan identifier');
			}
		} finally {
			identifierSaving = false;
		}
	}
</script>

<div class="space-y-6">
	<div class="flex flex-wrap gap-4 justify-between items-center">
		<div>
			<h1 class="text-3xl font-bold text-base-content">Data Pegawai</h1>
			<p class="text-base-content opacity-70 mt-1">Kelola daftar pegawai beserta identifier dinamis</p>
		</div>
		<button class="btn btn-success text-white" on:click={openCreateModal}>
			<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
			</svg>
			Tambah Pegawai
		</button>
	</div>

	<div class="card bg-base-100 shadow">
		<div class="card-body flex flex-col gap-4">
			<div class="flex flex-col md:flex-row gap-4 items-start md:items-center">
				<label class="input input-bordered flex items-center gap-2 w-full md:max-w-md">
					<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
					</svg>
					<input type="text" class="grow" placeholder="Cari nama / NIK / NPWP" bind:value={search} on:keydown={(e) => e.key === 'Enter' && handleSearch()} />
					<button class="btn btn-sm btn-primary text-white" on:click={handleSearch}>Cari</button>
				</label>
				<div class="flex items-center gap-2">
					<span class="text-sm text-base-content opacity-70">Row per halaman</span>
					<select class="select select-bordered select-sm" bind:value={pagination.per_page} on:change={(e) => changePerPage(Number(e.currentTarget.value))}>
						{#each perPageOptions as option}
							<option value={option}>{option}</option>
						{/each}
					</select>
				</div>
			</div>
		</div>
	</div>

	{#if loading}
		<div class="flex justify-center items-center min-h-[400px]">
			<span class="loading loading-spinner loading-lg text-primary"></span>
		</div>
	{:else}
		<div class="card bg-base-100 shadow">
			<div class="card-body p-0">
				<div class="overflow-x-auto">
					<table class="table table-zebra">
						<thead>
							<tr>
								<th class="text-base-content">Nama</th>
								<th class="text-base-content">NIK</th>
								<th class="text-base-content">NPWP</th>
								<th class="text-base-content">Identifier</th>
								<th class="text-base-content text-right">Aksi</th>
							</tr>
						</thead>
						<tbody>
							{#if persons.length > 0}
								{#each persons as person}
									<tr>
										<td class="font-semibold text-base-content">{person.full_name}</td>
										<td class="text-base-content opacity-70">{person.nik || '-'}</td>
										<td class="text-base-content opacity-70">{person.npwp || '-'}</td>
										<td>
											<div class="flex flex-wrap gap-1">
												{#if person.identifiers && person.identifiers.length > 0}
													{#each person.identifiers as identifier}
														<span class="badge badge-ghost badge-sm">
															{identifier.scheme?.code || 'ID'}: {identifier.raw_value}
														</span>
													{/each}
												{:else}
													<span class="text-xs text-base-content opacity-50">Belum ada ID</span>
												{/if}
											</div>
										</td>
										<td class="text-right">
											<div class="join justify-end">
												<button class="btn btn-sm btn-ghost join-item" on:click={() => openEditModal(person)}>
													Edit
												</button>
											</div>
										</td>
									</tr>
								{/each}
							{:else}
								<tr>
									<td colspan="5" class="text-center text-base-content opacity-60 py-10">
										Belum ada data pegawai
									</td>
								</tr>
							{/if}
						</tbody>
					</table>
				</div>

				{#if persons.length > 0}
					<div class="flex flex-col md:flex-row justify-between items-center gap-4 p-4">
						<div class="text-sm text-base-content opacity-70">
							Halaman {pagination.current_page} dari {pagination.last_page} · Total {pagination.total} data
						</div>
						<div class="join">
							<button class="btn btn-sm join-item" disabled={pagination.current_page === 1} on:click={() => loadPersons(pagination.current_page - 1)}>
								«
							</button>
							<button class="btn btn-sm join-item" disabled>
								{pagination.current_page}
							</button>
							<button class="btn btn-sm join-item" disabled={pagination.current_page === pagination.last_page} on:click={() => loadPersons(pagination.current_page + 1)}>
								»
							</button>
						</div>
					</div>
				{/if}
			</div>
		</div>
	{/if}
</div>

<!-- Form Modal -->
{#if showFormModal}
	<div class="modal modal-open">
		<div class="modal-box max-w-3xl">
			<h3 class="text-2xl font-bold text-base-content mb-1">
				{formMode === 'create' ? 'Tambah Pegawai' : 'Edit Pegawai'}
			</h3>
			<p class="text-sm text-base-content opacity-60 mb-4">
				Masukkan data identitas pegawai dan kelola identifier sesuai skema
			</p>

			<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
				<div class="form-control">
					<div class="label pb-1"><span class="label-text font-semibold text-base-content">Nama Lengkap <span class="text-error">*</span></span></div>
					<input
						type="text"
						class={`input input-bordered w-full text-base-content placeholder:text-base-content/50 ${formErrors.full_name ? 'input-error' : ''}`}
						placeholder="Nama sesuai KTP"
						bind:value={formData.full_name}
					/>
					{#if formErrors.full_name}
						<div class="label pt-1 pb-0"><span class="label-text-alt text-error">{formErrors.full_name}</span></div>
					{/if}
				</div>

				<div class="form-control">
					<div class="label pb-1"><span class="label-text font-semibold text-base-content">Tanggal Lahir</span></div>
					<input type="date" class="input input-bordered w-full text-base-content" bind:value={formData.birth_date} />
				</div>

				<div class="form-control">
					<div class="label pb-1"><span class="label-text font-semibold text-base-content">NIK</span></div>
					<input
						type="text"
						maxlength="16"
						class={`input input-bordered w-full text-base-content placeholder:text-base-content/50 ${formErrors.nik ? 'input-error' : ''}`}
						placeholder="16 digit NIK"
						bind:value={formData.nik}
					/>
					{#if formErrors.nik}
						<div class="label pt-1 pb-0"><span class="label-text-alt text-error">{formErrors.nik}</span></div>
					{/if}
				</div>

				<div class="form-control">
					<div class="label pb-1"><span class="label-text font-semibold text-base-content">NPWP</span></div>
					<input
						type="text"
						class={`input input-bordered w-full text-base-content placeholder:text-base-content/50 ${formErrors.npwp ? 'input-error' : ''}`}
						placeholder="NPWP tanpa tanda baca"
						bind:value={formData.npwp}
					/>
					{#if formErrors.npwp}
						<div class="label pt-1 pb-0"><span class="label-text-alt text-error">{formErrors.npwp}</span></div>
					{/if}
				</div>
			</div>

			{#if formMode === 'edit'}
				<div class="mt-6 border-t border-base-200 pt-4 space-y-4">
					<div class="flex justify-between items-center">
						<div>
							<h4 class="font-semibold text-base-content">Identifier Tambahan</h4>
							<p class="text-sm text-base-content opacity-60">Gunakan skema ID agar konsisten dan valid</p>
						</div>
					</div>

					<div class="overflow-x-auto rounded-xl border border-base-200">
						<table class="table table-sm">
							<thead>
								<tr>
									<th>Skema</th>
									<th>Nilai</th>
									<th>Terdaftar</th>
								</tr>
							</thead>
							<tbody>
								{#if identifierList.length > 0}
									{#each identifierList as identifier}
										<tr>
											<td class="font-semibold">{identifier.scheme?.label || identifier.scheme?.code || 'ID'}</td>
											<td>{identifier.raw_value}</td>
											<td class="text-sm text-base-content opacity-60">
												{new Date(identifier.created_at || editingPerson?.created_at || '').toLocaleString('id-ID')}
											</td>
										</tr>
									{/each}
								{:else}
									<tr>
										<td colspan="3" class="text-center text-base-content opacity-60 py-4">Belum ada identifier tambahan</td>
									</tr>
								{/if}
							</tbody>
						</table>
					</div>

					<div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
						<div class="form-control">
							<div class="label pb-1"><span class="label-text font-semibold text-base-content">Skema</span></div>
							<select class={`select select-bordered text-base-content ${identifierErrors.scheme_id ? 'select-error' : ''}`} bind:value={identifierForm.scheme_id}>
								<option value="" disabled>Pilih skema</option>
								{#each identifierSchemes as scheme}
									<option value={scheme.id}>{scheme.label} ({scheme.code})</option>
								{/each}
							</select>
							{#if identifierErrors.scheme_id}
								<div class="label pt-1 pb-0"><span class="label-text-alt text-error">{identifierErrors.scheme_id}</span></div>
							{/if}
						</div>
						<div class="form-control md:col-span-2">
							<div class="label pb-1"><span class="label-text font-semibold text-base-content">Nilai Identifier</span></div>
							<input
								type="text"
								class={`input input-bordered w-full text-base-content placeholder:text-base-content/50 ${identifierErrors.raw_value ? 'input-error' : ''}`}
								placeholder={selectedScheme?.example || 'Masukkan nilai sesuai skema'}
								bind:value={identifierForm.raw_value}
							/>
							<div class="label pt-1 pb-0 flex flex-col gap-1 items-start">
								{#if identifierErrors.raw_value}
									<span class="label-text-alt text-error">{identifierErrors.raw_value}</span>
								{:else if selectedScheme}
									<span class="label-text-alt text-base-content opacity-60">
										Panjang {selectedScheme.length_min || '-'} - {selectedScheme.length_max || '-'} · Normalisasi: {selectedScheme.normalize_rule}
									</span>
									{#if normalizedPreview}
										<span class="label-text-alt text-base-content opacity-60">
											Preview: {normalizedPreview}
										</span>
									{/if}
								{/if}
							</div>
						</div>
					</div>
					<div class="flex justify-end">
						<button class="btn btn-primary text-white" on:click={addIdentifier} disabled={identifierSaving}>
							{#if identifierSaving}
								<span class="loading loading-spinner loading-sm"></span>
								Menyimpan...
							{:else}
								Tambah Identifier
							{/if}
						</button>
					</div>
				</div>
			{/if}

			<div class="modal-action mt-6">
				<button class="btn btn-outline btn-neutral" on:click={closeFormModal} disabled={saving}>Batal</button>
				<button class="btn btn-success text-white" on:click={savePerson} disabled={saving}>
					{#if saving}
						<span class="loading loading-spinner loading-sm"></span>
						Menyimpan...
					{:else}
						Simpan
					{/if}
				</button>
			</div>
		</div>
		<form method="dialog" class="modal-backdrop">
			<button on:click={closeFormModal}>close</button>
		</form>
	</div>
{/if}


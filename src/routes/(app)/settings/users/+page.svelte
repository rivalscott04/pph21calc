<script lang="ts">
	import { onMount } from 'svelte';
	import { tenantsApi, type Tenant, type TenantUser } from '$lib/api/tenants.js';
	import { auth } from '$lib/stores/auth.js';
	import { get } from 'svelte/store';
	import { toast } from '$lib/stores/toast.js';

	let loading = true;
	let users: TenantUser[] = [];
	let tenants: Tenant[] = [];
	let selectedTenantId: number | null = null;
	let showModal = false;
	let saving = false;

	// Form state
	let formData = {
		name: '',
		email: '',
		password: '',
		role: 'HR',
		status: 'active' as 'active' | 'inactive'
	};

	// Form errors
	let formErrors: Record<string, string> = {};

	// Roles
	const roles = [
		{ value: 'TENANT_ADMIN', label: 'Tenant Admin', description: 'Kelola struktur organisasi, pegawai, user-role di tenant' },
		{ value: 'HR', label: 'HR / Payroll Officer', description: 'Input penghasilan, hitung payroll, generate slip gaji' },
		{ value: 'FINANCE', label: 'Finance / Tax Officer', description: 'Review PPh21, approval payroll, ekspor CoreTax' },
		{ value: 'VIEWER', label: 'Viewer / Auditor', description: 'Read-only terhadap laporan dan payroll' }
	];

	const currentUser = get(auth);
	const isSuperadmin = currentUser?.user?.is_superadmin ?? false;

	function openCreateModal() {
		formData = {
			name: '',
			email: '',
			password: '',
			role: 'HR',
			status: 'active'
		};
		formErrors = {};
		showModal = true;
	}

	function closeModal() {
		showModal = false;
		formData = {
			name: '',
			email: '',
			password: '',
			role: 'HR',
			status: 'active'
		};
		formErrors = {};
	}

	function validateForm(): boolean {
		formErrors = {};

		if (!formData.name.trim()) {
			formErrors.name = 'Nama wajib diisi';
		}

		if (!formData.email.trim()) {
			formErrors.email = 'Email wajib diisi';
		} else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
			formErrors.email = 'Format email tidak valid';
		}

		if (!formData.password) {
			formErrors.password = 'Password wajib diisi';
		} else if (formData.password.length < 8) {
			formErrors.password = 'Password minimal 8 karakter';
		}

		return Object.keys(formErrors).length === 0;
	}

	async function saveUser() {
		if (!validateForm()) {
			toast.error('Mohon perbaiki error pada form');
			return;
		}

		saving = true;
		try {
			// If superadmin, use tenant-specific endpoint
			if (isSuperadmin) {
				const targetTenantId = selectedTenantId || currentUser?.tenant?.id;
				if (!targetTenantId) {
					toast.error('Tenant tidak ditemukan');
					saving = false;
					return;
				}
				await tenantsApi.createUser(targetTenantId, {
					name: formData.name.trim(),
					email: formData.email.trim().toLowerCase(),
					password: formData.password,
					role: formData.role
				});
			} else {
				// If tenant admin, use my-tenant endpoint
				await tenantsApi.createUserInMyTenant({
					name: formData.name.trim(),
					email: formData.email.trim().toLowerCase(),
					password: formData.password,
					role: formData.role,
					status: formData.status
				});
			}
			toast.success('User berhasil dibuat');
			closeModal();
			await loadUsers();
		} catch (error: any) {
			console.error('Failed to create user:', error);
			if (error.errors) {
				formErrors = error.errors;
			} else {
				toast.error(error.message || 'Gagal membuat user');
			}
		} finally {
			saving = false;
		}
	}

	async function loadTenants() {
		if (!isSuperadmin) return;
		try {
			tenants = await tenantsApi.list();
			if (tenants.length > 0 && !selectedTenantId) {
				selectedTenantId = tenants[0].id;
			}
		} catch (error) {
			console.error('Failed to load tenants:', error);
			toast.error('Gagal memuat daftar tenant');
		}
	}

	async function loadUsers() {
		loading = true;
		try {
			let response: any;
			
			// If superadmin, use tenant-specific endpoint
			if (isSuperadmin) {
				const targetTenantId = selectedTenantId || currentUser?.tenant?.id;
				if (!targetTenantId) {
					users = [];
					loading = false;
					return;
				}
				response = await tenantsApi.listUsers(targetTenantId);
			} else {
				// If tenant admin, use my-tenant endpoint
				response = await tenantsApi.listMyTenantUsers();
			}
			// Handle paginated response or direct array
			if (Array.isArray(response)) {
				users = response;
			} else if (response?.data && Array.isArray(response.data)) {
				users = response.data;
			} else {
				// If response is paginated object, extract data
				users = response?.data || [];
			}
		} catch (error) {
			console.error('Failed to load users:', error);
			toast.error('Gagal memuat daftar user');
			users = [];
		}
	}

	function getRoleLabel(role: string): string {
		const roleObj = roles.find(r => r.value === role);
		return roleObj?.label || role;
	}

	function getRoleDescription(role: string): string {
		const roleObj = roles.find(r => r.value === role);
		return roleObj?.description || '';
	}

	onMount(async () => {
		await loadTenants();
		await loadUsers();
		loading = false;
	});

	$: if (selectedTenantId) {
		loadUsers();
	}
</script>

<div class="space-y-6">
	<div class="flex justify-between items-center">
		<div>
			<h1 class="text-3xl font-bold text-base-content">User & Role</h1>
			<p class="text-base-content opacity-70 mt-1">Kelola users dan role di tenant</p>
		</div>
		<button class="btn btn-success text-white" on:click={openCreateModal}>
			<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
			</svg>
			Tambah User
		</button>
	</div>

	{#if isSuperadmin && tenants.length > 0}
		<div class="card bg-base-100 shadow-lg">
			<div class="card-body">
				<label class="form-control w-full max-w-xs">
					<div class="label">
						<span class="label-text font-semibold text-base-content">Pilih Tenant</span>
					</div>
					<select class="select select-bordered text-neutral bg-base-100" bind:value={selectedTenantId}>
						{#each tenants as tenant}
							<option value={tenant.id} class="text-neutral bg-base-100">{tenant.name} ({tenant.code})</option>
						{/each}
					</select>
				</label>
			</div>
		</div>
	{/if}

	{#if loading}
		<div class="flex justify-center items-center min-h-[400px]">
			<span class="loading loading-spinner loading-lg text-primary"></span>
		</div>
	{:else}
		<!-- Users List -->
		<div class="card bg-base-100 shadow-lg">
			<div class="card-body">
				{#if users.length > 0}
					<div class="overflow-x-auto">
						<table class="table table-zebra">
							<thead>
								<tr>
									<th class="text-base-content">Nama</th>
									<th class="text-base-content">Email</th>
									<th class="text-base-content">Role</th>
									<th class="text-base-content">Status</th>
								</tr>
							</thead>
							<tbody>
								{#each users as tenantUser}
									<tr>
										<td class="text-base-content font-medium">{tenantUser.user?.name || '-'}</td>
										<td class="text-base-content opacity-70">{tenantUser.user?.email || '-'}</td>
										<td>
											<div class="flex flex-col">
												<span class="badge badge-primary badge-sm text-white">{getRoleLabel(tenantUser.role)}</span>
												<span class="text-xs text-base-content opacity-50 mt-1">{getRoleDescription(tenantUser.role)}</span>
											</div>
										</td>
										<td>
											<span class="badge {(tenantUser.status || tenantUser.user?.status || 'active') === 'active' ? 'badge-success' : 'badge-error'} badge-sm">
												{(tenantUser.status || tenantUser.user?.status || 'active') === 'active' ? 'Aktif' : 'Nonaktif'}
											</span>
										</td>
									</tr>
								{/each}
							</tbody>
						</table>
					</div>
				{:else}
					<div class="text-center py-12">
						<p class="text-base-content opacity-50 mb-4">Belum ada user di tenant ini</p>
						<button class="btn btn-success text-white" on:click={openCreateModal}>
							<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
							</svg>
							Tambah User Pertama
						</button>
					</div>
				{/if}
			</div>
		</div>
	{/if}
</div>

<!-- Modal Create User -->
{#if showModal}
	<div class="modal modal-open">
		<div class="modal-box max-w-2xl">
			<div class="mb-6">
				<h3 class="text-2xl font-bold text-base-content mb-1">Tambah User Baru</h3>
				<p class="text-sm text-base-content opacity-60">Buat user baru untuk tenant</p>
			</div>

			<div class="space-y-5">
				<!-- Name -->
				<div class="form-control">
					<div class="label pb-1">
						<span class="label-text font-semibold text-base-content">Nama <span class="text-error">*</span></span>
					</div>
					<input
						type="text"
						class={`input input-bordered w-full text-base-content placeholder:text-base-content/50 ${formErrors.name ? 'input-error' : ''}`}
						placeholder="John Doe"
						bind:value={formData.name}
					/>
					{#if formErrors.name}
						<div class="label pt-1 pb-0">
							<span class="label-text-alt text-error">{formErrors.name}</span>
						</div>
					{/if}
				</div>

				<!-- Email -->
				<div class="form-control">
					<div class="label pb-1">
						<span class="label-text font-semibold text-base-content">Email <span class="text-error">*</span></span>
					</div>
					<input
						type="email"
						class={`input input-bordered w-full text-base-content placeholder:text-base-content/50 ${formErrors.email ? 'input-error' : ''}`}
						placeholder="john.doe@example.com"
						bind:value={formData.email}
					/>
					{#if formErrors.email}
						<div class="label pt-1 pb-0">
							<span class="label-text-alt text-error">{formErrors.email}</span>
						</div>
					{/if}
				</div>

				<!-- Password -->
				<div class="form-control">
					<div class="label pb-1">
						<span class="label-text font-semibold text-base-content">Password <span class="text-error">*</span></span>
					</div>
					<input
						type="password"
						class={`input input-bordered w-full text-base-content placeholder:text-base-content/50 ${formErrors.password ? 'input-error' : ''}`}
						placeholder="Minimal 8 karakter"
						bind:value={formData.password}
					/>
					{#if formErrors.password}
						<div class="label pt-1 pb-0">
							<span class="label-text-alt text-error">{formErrors.password}</span>
						</div>
					{:else}
						<div class="label pt-1 pb-0">
							<span class="label-text-alt text-base-content opacity-50">Minimal 8 karakter</span>
						</div>
					{/if}
				</div>

				<!-- Role -->
				<div class="form-control">
					<div class="label pb-1">
						<span class="label-text font-semibold text-base-content">Role <span class="text-error">*</span></span>
					</div>
					<select class="select select-bordered w-full text-neutral bg-base-100" bind:value={formData.role}>
						{#each roles as role}
							<option value={role.value} class="text-neutral bg-base-100">{role.label}</option>
						{/each}
					</select>
					<div class="label pt-1 pb-0">
						<span class="label-text-alt text-base-content opacity-50">{getRoleDescription(formData.role)}</span>
					</div>
				</div>

				<!-- Status -->
				<div class="form-control">
					<div class="label pb-1">
						<span class="label-text font-semibold text-base-content">Status</span>
					</div>
					<select class="select select-bordered w-full text-neutral bg-base-100" bind:value={formData.status}>
						<option value="active" class="text-neutral bg-base-100">Aktif</option>
						<option value="inactive" class="text-neutral bg-base-100">Nonaktif</option>
					</select>
				</div>
			</div>

			<div class="modal-action mt-8 pt-6 border-t border-base-300">
				<button class="btn btn-outline btn-neutral text-base-content" on:click={closeModal} disabled={saving}>
					Batal
				</button>
				<button class="btn btn-success text-white" on:click={saveUser} disabled={saving}>
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

<style>
	/* Override global select color to use dark navy/black instead of --bc */
	.select,
	select {
		color: hsl(215 16% 27%) !important; /* neutral color - dark navy blue */
	}
	
	.select option,
	select option {
		color: hsl(215 16% 27%) !important; /* neutral color - dark navy blue */
		background-color: hsl(var(--b1)) !important;
	}
</style>

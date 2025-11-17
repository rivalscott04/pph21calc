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
let confirmTenant = false;
let showPassword = false;
let passwordManuallyEdited = false;
let showPasswordMeter = false;

type PasswordStrengthState = {
	score: number;
	percent: number;
	label: string;
	colorClass: string;
};

let passwordStrength: PasswordStrengthState = calculatePasswordStrength('');

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
let selectedTenant: Partial<Tenant> | null | undefined;
$: selectedTenant =
	isSuperadmin
		? tenants.find((tenant) => tenant.id === (selectedTenantId ?? tenants[0]?.id))
		: currentUser?.tenant;
$: showPasswordMeter = passwordManuallyEdited && formData.password.length > 0 && !formErrors.password;

function resetPasswordHelpers() {
	showPassword = false;
	passwordManuallyEdited = false;
	updatePasswordStrengthState('');
}

function openCreateModal() {
	formData = {
		name: '',
		email: '',
		password: '',
		role: 'HR',
		status: 'active'
	};
	formErrors = {};
	confirmTenant = false;
	resetPasswordHelpers();
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
	confirmTenant = false;
	resetPasswordHelpers();
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
	} finally {
		loading = false;
	}
}

function togglePasswordVisibility() {
	showPassword = !showPassword;
}

function handlePasswordInput(event: Event) {
	const target = event.currentTarget as HTMLInputElement;
	const value = target.value;
	formData = { ...formData, password: value };
	passwordManuallyEdited = true;
	updatePasswordStrengthState(value);
	clearPasswordError();
}

function generateSecurePassword(length = 12) {
	const upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
	const lower = 'abcdefghijklmnopqrstuvwxyz';
	const numbers = '0123456789';
	const symbols = '!@#$%^&*()-_=+[]{};:,.?/|';
	const all = upper + lower + numbers + symbols;

	const pickChar = (source: string) => source[getRandomInt(source.length)];

	let password = '';
	password += pickChar(upper);
	password += pickChar(lower);
	password += pickChar(numbers);
	password += pickChar(symbols);

	for (let i = password.length; i < length; i++) {
		password += pickChar(all);
	}

	// Secure shuffle (Fisher-Yates)
	const chars = password.split('');
	for (let i = chars.length - 1; i > 0; i--) {
		const j = getRandomInt(i + 1);
		[chars[i], chars[j]] = [chars[j], chars[i]];
	}

	return chars.join('');
}

function handleGeneratePassword() {
	const generated = generateSecurePassword(12);
	formData = { ...formData, password: generated };
	passwordManuallyEdited = false;
	updatePasswordStrengthState(generated);
	clearPasswordError();
	toast.success('Password acak berhasil dibuat');
}

function updatePasswordStrengthState(password: string) {
	passwordStrength = calculatePasswordStrength(password);
}

function calculatePasswordStrength(password: string): PasswordStrengthState {
	let score = 0;
	if (password.length >= 8) score++;
	if (password.length >= 12) score++;
	if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
	if (/\d/.test(password)) score++;
	if (/[^A-Za-z0-9]/.test(password)) score++;

	const percent = (score / 5) * 100;

	let label = 'Sangat lemah';
	let colorClass = 'progress-error';

	if (score >= 4) {
		label = 'Kuat';
		colorClass = 'progress-success';
	} else if (score === 3) {
		label = 'Sedang';
		colorClass = 'progress-warning';
	} else if (score === 2) {
		label = 'Lemah';
		colorClass = 'progress-warning';
	}

	return { score, percent, label, colorClass };
}

async function copyPassword() {
	if (!formData.password) return;
	try {
		if (typeof navigator !== 'undefined' && navigator?.clipboard?.writeText) {
			await navigator.clipboard.writeText(formData.password);
		} else {
			fallbackCopyToClipboard(formData.password);
		}
		toast.success('Password berhasil disalin');
	} catch (error) {
		console.error('Failed to copy password:', error);
		toast.error('Gagal menyalin password');
	}
}

function fallbackCopyToClipboard(text: string) {
	if (typeof document === 'undefined') return;
	const textarea = document.createElement('textarea');
	textarea.value = text;
	textarea.style.position = 'fixed';
	textarea.style.left = '-9999px';
	document.body.appendChild(textarea);
	textarea.focus();
	textarea.select();
	document.execCommand('copy');
	document.body.removeChild(textarea);
}

function getRandomInt(max: number) {
	if (typeof crypto !== 'undefined' && crypto.getRandomValues) {
		const array = new Uint32Array(1);
		crypto.getRandomValues(array);
		return array[0] % max;
	}
	return Math.floor(Math.random() * max);
}

function clearPasswordError() {
	if (formErrors.password) {
		const updatedErrors = { ...formErrors };
		delete updatedErrors.password;
		formErrors = updatedErrors;
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
		{#if isSuperadmin}
			<div class="alert alert-warning mb-4 gap-4">
				<div class="flex-1">
					<p class="font-semibold text-base-content">Tenant tujuan</p>
					<p class="text-base-content">
						{#if selectedTenant}
							<span class="font-medium">{selectedTenant.name}</span>
							{#if selectedTenant?.code}
								<span class="opacity-60">({selectedTenant.code})</span>
							{/if}
						{:else}
							<span class="text-error">Belum memilih tenant</span>
						{/if}
					</p>
				</div>
				<label class="label cursor-pointer gap-3">
					<span class="label-text text-sm text-base-content">Saya sudah mengecek tenant tujuan</span>
					<input type="checkbox" class="checkbox checkbox-warning" bind:checked={confirmTenant} />
				</label>
			</div>
		{/if}

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
					<div class="space-y-2">
						<div class="flex flex-col gap-2 sm:flex-row">
							<div class="relative flex-1">
								<input
									type={showPassword ? 'text' : 'password'}
									class={`input input-bordered w-full pr-12 text-base-content placeholder:text-base-content/50 ${formErrors.password ? 'input-error' : ''}`}
									placeholder="Minimal 8 karakter"
									value={formData.password}
									on:input={handlePasswordInput}
								/>
								<button
									type="button"
									class="btn btn-ghost btn-xs absolute right-2 top-1/2 -translate-y-1/2 text-base-content"
									on:click={togglePasswordVisibility}
									aria-label={showPassword ? 'Sembunyikan password' : 'Tampilkan password'}
								>
									{#if showPassword}
										<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
											<path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18" />
											<path stroke-linecap="round" stroke-linejoin="round" d="M10.58 10.58A2 2 0 0114 12a2 2 0 01-2 2c-.51 0-.98-.19-1.35-.5" />
											<path stroke-linecap="round" stroke-linejoin="round" d="M6.53 6.53C4.09 8.25 2.5 10.61 2.5 12c0 1.39 3.73 6.5 9.5 6.5 1.57 0 3-.33 4.29-.9" />
											<path stroke-linecap="round" stroke-linejoin="round" d="M17.47 17.47C19.91 15.75 21.5 13.39 21.5 12c0-1.39-3.73-6.5-9.5-6.5-1.11 0-2.16.15-3.15.43" />
										</svg>
									{:else}
										<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
											<path stroke-linecap="round" stroke-linejoin="round" d="M1.5 12c0 1.39 3.73 6.5 9.5 6.5s9.5-5.11 9.5-6.5-3.73-6.5-9.5-6.5S1.5 10.61 1.5 12z" />
											<circle cx="11" cy="12" r="3" />
										</svg>
									{/if}
								</button>
							</div>
							<button type="button" class="btn btn-outline btn-primary whitespace-nowrap" on:click={handleGeneratePassword}>
								Generate
							</button>
							{#if formData.password}
								<button type="button" class="btn btn-ghost text-primary whitespace-nowrap" on:click={copyPassword}>
									Salin
								</button>
							{/if}
						</div>
						{#if formErrors.password}
							<div class="label pt-1 pb-0">
								<span class="label-text-alt text-error">{formErrors.password}</span>
							</div>
						{:else if showPasswordMeter}
							<div class="space-y-1">
								<progress class={`progress ${passwordStrength.colorClass} w-full`} value={passwordStrength.percent} max="100"></progress>
								<div class="flex items-center justify-between text-xs text-base-content">
									<span class="font-semibold">{passwordStrength.label}</span>
									<span class="opacity-70">{Math.round(passwordStrength.percent)}%</span>
								</div>
							</div>
						{:else}
							<div class="label pt-1 pb-0">
								<span class="label-text-alt text-base-content opacity-50">Minimal 8 karakter</span>
							</div>
						{/if}
					</div>
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
			<button class="btn btn-success text-white" on:click={saveUser} disabled={saving || (isSuperadmin && !confirmTenant)}>
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

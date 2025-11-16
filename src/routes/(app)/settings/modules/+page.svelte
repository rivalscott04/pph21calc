<script lang="ts">
	import { onMount, onDestroy } from 'svelte';
	import { modules, type ModuleConfig } from '$lib/stores/modules.js';
	import { toast } from '$lib/stores/toast.js';

	let loading = true;
	let saving = false;
	let form: ModuleConfig | null = null;

	const unsubscribe = modules.subscribe(($modules) => {
		if ($modules) {
			form = {
				core_payroll: $modules.core_payroll,
				coretax_integration: $modules.coretax_integration,
				compliance_ojk: $modules.compliance_ojk,
				compliance_pdp: $modules.compliance_pdp,
				audit_trail: $modules.audit_trail,
				bpjs_integration: $modules.bpjs_integration,
				syariah_extension: $modules.syariah_extension
			};
		}
	});

	onDestroy(() => {
		unsubscribe?.();
	});

	onMount(async () => {
		try {
			loading = true;
			await modules.load();
		} catch (error) {
			console.error('Failed to load modules:', error);
			toast.error('Gagal memuat konfigurasi modul');
		} finally {
			loading = false;
		}
	});

	function toggleModule(key: keyof ModuleConfig) {
		if (!form) return;
		form = {
			...form,
			[key]: !form[key]
		};
	}

	async function saveChanges() {
		if (!form) return;
		saving = true;
		try {
			await modules.update(form);
		} catch (error) {
			console.error('Failed to update modules:', error);
		} finally {
			saving = false;
		}
	}

	const moduleSections = [
		{
			title: 'Modul Inti',
			description: 'Kontrol fitur utama yang mempengaruhi seluruh aplikasi.',
			items: [
				{
					key: 'core_payroll',
					label: 'Core Payroll',
					description: 'Modul penggajian utama untuk perhitungan PPh 21 dan slip gaji.',
					tag: 'Wajib aktif',
					disabled: true
				},
				{
					key: 'audit_trail',
					label: 'Audit Trail',
					description: 'Aktifkan pencatatan aktivitas untuk setiap perubahan data penting.',
					tag: 'Direkomendasikan'
				}
			]
		},
		{
			title: 'Integrasi Eksternal',
			description: 'Kontrol modul yang berhubungan dengan layanan pihak ketiga.',
			items: [
				{
					key: 'coretax_integration',
					label: 'CoreTax Integration',
					description: 'Generate BPA1/BPA2 dan upload langsung ke CoreTax.',
					tag: 'Perlu akses CoreTax'
				},
				{
					key: 'bpjs_integration',
					label: 'BPJS Integration',
					description: 'Sinkronisasi iuran dan data kepesertaan BPJS.',
					tag: 'Segera hadir'
				}
			]
		},
		{
			title: 'Kepatuhan & Regulasi',
			description: 'Aktifkan modul pendukung regulasi khusus.',
			items: [
				{
					key: 'compliance_ojk',
					label: 'Compliance OJK',
					description: 'Template pelaporan berkala sesuai regulasi OJK.',
					tag: 'Finansial'
				},
				{
					key: 'compliance_pdp',
					label: 'Compliance PDP',
					description: 'Pengaturan privasi dan perlindungan data personal.',
					tag: 'Privasi'
				}
			]
		},
		{
			title: 'Ekstensi Bisnis',
			description: 'Fitur tambahan untuk model bisnis tertentu.',
			items: [
				{
					key: 'syariah_extension',
					label: 'Syariah Extension',
					description: 'Penyesuaian kebijakan payroll untuk entitas berbasis syariah.',
					tag: 'Opsional'
				}
			]
		}
	];
</script>

<div class="space-y-8">
	<div class="flex flex-wrap items-center gap-4 justify-between">
		<div>
			<h1 class="text-3xl font-bold text-base-content">Modul & Feature Flags</h1>
			<p class="text-base-content opacity-80 mt-1">
				Aktifkan atau nonaktifkan modul sesuai kebutuhan tenant. Perubahan akan langsung diterapkan setelah disimpan.
			</p>
		</div>
		<button class="btn btn-success text-white" on:click={saveChanges} disabled={saving || loading || !form}>
			{#if saving}
				<span class="loading loading-spinner loading-sm"></span>
				Menyimpan...
			{:else}
				Simpan Perubahan
			{/if}
		</button>
	</div>

	{#if loading || !form}
		<div class="flex justify-center items-center min-h-[280px]">
			<span class="loading loading-spinner loading-lg text-primary"></span>
		</div>
	{:else}
		<div class="grid gap-6">
			{#each moduleSections as section}
				<div class="card bg-base-100 shadow">
					<div class="card-body space-y-4">
						<div>
							<h2 class="card-title text-base-content">{section.title}</h2>
							<p class="text-base-content opacity-70">{section.description}</p>
						</div>

						<div class="space-y-4">
							{#each section.items as item}
								<div class="flex flex-col md:flex-row md:items-center gap-4 p-4 border border-base-300 rounded-2xl">
									<div class="flex-1">
										<div class="flex items-center gap-2">
											<h3 class="text-base font-semibold text-base-content">{item.label}</h3>
											{#if item.tag}
												<span class="badge badge-ghost badge-sm">{item.tag}</span>
											{/if}
										</div>
										<p class="text-sm text-base-content opacity-70 mt-1">{item.description}</p>
									</div>
									<label class="flex items-center gap-3">
										<span class="text-sm font-semibold text-base-content">{form[item.key] ? 'Aktif' : 'Nonaktif'}</span>
										<input
											type="checkbox"
											class="toggle toggle-success"
											checked={form[item.key]}
											on:change={() => toggleModule(item.key)}
											disabled={item.disabled || saving}
										/>
									</label>
								</div>
							{/each}
						</div>
					</div>
				</div>
			{/each}
		</div>
	{/if}
</div>
	let saving = false;
	let form: ModuleConfig | null = null;

	const unsubscribe = modules.subscribe(($modules) => {
		if ($modules) {
			form = {
				core_payroll: $modules.core_payroll,
				coretax_integration: $modules.coretax_integration,
				compliance_ojk: $modules.compliance_ojk,
				compliance_pdp: $modules.compliance_pdp,
				audit_trail: $modules.audit_trail,
				bpjs_integration: $modules.bpjs_integration,
				syariah_extension: $modules.syariah_extension
			};
		}
	});

	onDestroy(() => {
		unsubscribe?.();
	});

	onMount(async () => {
		try {
			loading = true;
			await modules.load();
		} catch (error) {
			console.error('Failed to load modules:', error);
			toast.error('Gagal memuat konfigurasi modul');
		} finally {
			loading = false;
		}
	});

	function toggleModule(key: keyof ModuleConfig) {
		if (!form) return;
		form = {
			...form,
			[key]: !form[key]
		};
	}

	async function saveChanges() {
		if (!form) return;
		saving = true;
		try {
			await modules.update(form);
		} catch (error) {
			console.error('Failed to update modules:', error);
		} finally {
			saving = false;
		}
	}

	const moduleSections = [
		{
			title: 'Modul Inti',
			description: 'Kontrol fitur utama yang mempengaruhi seluruh aplikasi.',
			items: [
				{
					key: 'core_payroll',
					label: 'Core Payroll',
					description: 'Modul penggajian utama untuk perhitungan PPh 21 dan slip gaji.',
					tag: 'Wajib aktif',
					disabled: true
				},
				{
					key: 'audit_trail',
					label: 'Audit Trail',
					description: 'Aktifkan pencatatan aktivitas untuk setiap perubahan data penting.',
					tag: 'Direkomendasikan'
				}
			]
		},
		{
			title: 'Integrasi Eksternal',
			description: 'Kontrol modul yang berhubungan dengan layanan pihak ketiga.',
			items: [
				{
					key: 'coretax_integration',
					label: 'CoreTax Integration',
					description: 'Generate BPA1/BPA2 dan upload langsung ke CoreTax.',
					tag: 'Perlu akses CoreTax'
				},
				{
					key: 'bpjs_integration',
					label: 'BPJS Integration',
					description: 'Sinkronisasi iuran dan data kepesertaan BPJS.',
					tag: 'Segera hadir'
				}
			]
		},
		{
			title: 'Kepatuhan & Regulasi',
			description: 'Aktifkan modul pendukung regulasi khusus.',
			items: [
				{
					key: 'compliance_ojk',
					label: 'Compliance OJK',
					description: 'Template pelaporan berkala sesuai regulasi OJK.',
					tag: 'Finansial'
				},
				{
					key: 'compliance_pdp',
					label: 'Compliance PDP',
					description: 'Pengaturan privasi dan perlindungan data personal.',
					tag: 'Privasi'
				}
			]
		},
		{
			title: 'Ekstensi Bisnis',
			description: 'Fitur tambahan untuk model bisnis tertentu.',
			items: [
				{
					key: 'syariah_extension',
					label: 'Syariah Extension',
					description: 'Penyesuaian kebijakan payroll untuk entitas berbasis syariah.',
					tag: 'Opsional'
				}
			]
		}
	];
</script>

<div class="space-y-8">
	<div class="flex flex-wrap items-center gap-4 justify-between">
		<div>
			<h1 class="text-3xl font-bold text-base-content">Modul & Feature Flags</h1>
			<p class="text-base-content opacity-80 mt-1">
				Aktifkan atau nonaktifkan modul sesuai kebutuhan tenant. Perubahan akan langsung diterapkan setelah disimpan.
			</p>
		</div>
		<button class="btn btn-success text-white" on:click={saveChanges} disabled={saving || loading || !form}>
			{#if saving}
				<span class="loading loading-spinner loading-sm"></span>
				Menyimpan...
			{:else}
				Simpan Perubahan
			{/if}
		</button>
	</div>

	{#if loading || !form}
		<div class="flex justify-center items-center min-h-[280px]">
			<span class="loading loading-spinner loading-lg text-primary"></span>
		</div>
	{:else}
		<div class="grid gap-6">
			{#each moduleSections as section}
				<div class="card bg-base-100 shadow">
					<div class="card-body space-y-4">
						<div>
							<h2 class="card-title text-base-content">{section.title}</h2>
							<p class="text-base-content opacity-70">{section.description}</p>
						</div>

						<div class="space-y-4">
							{#each section.items as item}
								<div class="flex flex-col md:flex-row md:items-center gap-4 p-4 border border-base-300 rounded-2xl">
									<div class="flex-1">
										<div class="flex items-center gap-2">
											<h3 class="text-base font-semibold text-base-content">{item.label}</h3>
											{#if item.tag}
												<span class="badge badge-ghost badge-sm">{item.tag}</span>
											{/if}
										</div>
										<p class="text-sm text-base-content opacity-70 mt-1">{item.description}</p>
									</div>
									<label class="flex items-center gap-3">
										<span class="text-sm font-semibold text-base-content">{form[item.key] ? 'Aktif' : 'Nonaktif'}</span>
										<input
											type="checkbox"
											class="toggle toggle-success"
											checked={form[item.key]}
											on:change={() => toggleModule(item.key)}
											disabled={item.disabled || saving}
										/>
									</label>
								</div>
							{/each}
						</div>
					</div>
				</div>
			{/each}
		</div>
	{/if}
</div>
	let saving = false;
	let form: ModuleConfig | null = null;

	const unsubscribe = modules.subscribe(($modules) => {
		if ($modules) {
			form = {
				core_payroll: $modules.core_payroll,
				coretax_integration: $modules.coretax_integration,
				compliance_ojk: $modules.compliance_ojk,
				compliance_pdp: $modules.compliance_pdp,
				audit_trail: $modules.audit_trail,
				bpjs_integration: $modules.bpjs_integration,
				syariah_extension: $modules.syariah_extension
			};
		}
	});

	onDestroy(() => {
		unsubscribe?.();
	});

	onMount(async () => {
		try {
			loading = true;
			await modules.load();
		} catch (error) {
			console.error('Failed to load modules:', error);
			toast.error('Gagal memuat konfigurasi modul');
		} finally {
			loading = false;
		}
	});

	function toggleModule(key: keyof ModuleConfig) {
		if (!form) return;
		form = {
			...form,
			[key]: !form[key]
		};
	}

	async function saveChanges() {
		if (!form) return;
		saving = true;
		try {
			await modules.update(form);
		} catch (error) {
			console.error('Failed to update modules:', error);
		} finally {
			saving = false;
		}
	}

	const moduleSections = [
		{
			title: 'Modul Inti',
			description: 'Kontrol fitur utama yang mempengaruhi seluruh aplikasi.',
			items: [
				{
					key: 'core_payroll',
					label: 'Core Payroll',
					description: 'Modul penggajian utama untuk perhitungan PPh 21 dan slip gaji.',
					tag: 'Wajib aktif',
					disabled: true
				},
				{
					key: 'audit_trail',
					label: 'Audit Trail',
					description: 'Aktifkan pencatatan aktivitas untuk setiap perubahan data penting.',
					tag: 'Direkomendasikan'
				}
			]
		},
		{
			title: 'Integrasi Eksternal',
			description: 'Kontrol modul yang berhubungan dengan layanan pihak ketiga.',
			items: [
				{
					key: 'coretax_integration',
					label: 'CoreTax Integration',
					description: 'Generate BPA1/BPA2 dan upload langsung ke CoreTax.',
					tag: 'Perlu akses CoreTax'
				},
				{
					key: 'bpjs_integration',
					label: 'BPJS Integration',
					description: 'Sinkronisasi iuran dan data kepesertaan BPJS.',
					tag: 'Segera hadir'
				}
			]
		},
		{
			title: 'Kepatuhan & Regulasi',
			description: 'Aktifkan modul pendukung regulasi khusus.',
			items: [
				{
					key: 'compliance_ojk',
					label: 'Compliance OJK',
					description: 'Template pelaporan berkala sesuai regulasi OJK.',
					tag: 'Finansial'
				},
				{
					key: 'compliance_pdp',
					label: 'Compliance PDP',
					description: 'Pengaturan privasi dan perlindungan data personal.',
					tag: 'Privasi'
				}
			]
		},
		{
			title: 'Ekstensi Bisnis',
			description: 'Fitur tambahan untuk model bisnis tertentu.',
			items: [
				{
					key: 'syariah_extension',
					label: 'Syariah Extension',
					description: 'Penyesuaian kebijakan payroll untuk entitas berbasis syariah.',
					tag: 'Opsional'
				}
			]
		}
	];
</script>

<div class="space-y-8">
	<div class="flex flex-wrap items-center gap-4 justify-between">
		<div>
			<h1 class="text-3xl font-bold text-base-content">Modul & Feature Flags</h1>
			<p class="text-base-content opacity-80 mt-1">
				Aktifkan atau nonaktifkan modul sesuai kebutuhan tenant. Perubahan akan langsung diterapkan setelah disimpan.
			</p>
		</div>
		<button class="btn btn-success text-white" on:click={saveChanges} disabled={saving || loading || !form}>
			{#if saving}
				<span class="loading loading-spinner loading-sm"></span>
				Menyimpan...
			{:else}
				Simpan Perubahan
			{/if}
		</button>
	</div>

	{#if loading || !form}
		<div class="flex justify-center items-center min-h-[280px]">
			<span class="loading loading-spinner loading-lg text-primary"></span>
		</div>
	{:else}
		<div class="grid gap-6">
			{#each moduleSections as section}
				<div class="card bg-base-100 shadow">
					<div class="card-body space-y-4">
						<div>
							<h2 class="card-title text-base-content">{section.title}</h2>
							<p class="text-base-content opacity-70">{section.description}</p>
						</div>

						<div class="space-y-4">
							{#each section.items as item}
								<div class="flex flex-col md:flex-row md:items-center gap-4 p-4 border border-base-300 rounded-2xl">
									<div class="flex-1">
										<div class="flex items-center gap-2">
											<h3 class="text-base font-semibold text-base-content">{item.label}</h3>
											{#if item.tag}
												<span class="badge badge-ghost badge-sm">{item.tag}</span>
											{/if}
										</div>
										<p class="text-sm text-base-content opacity-70 mt-1">{item.description}</p>
									</div>
									<label class="flex items-center gap-3">
										<span class="text-sm font-semibold text-base-content">{form[item.key] ? 'Aktif' : 'Nonaktif'}</span>
										<input
											type="checkbox"
											class="toggle toggle-success"
											checked={form[item.key]}
											on:change={() => toggleModule(item.key)}
											disabled={item.disabled || saving}
										/>
									</label>
								</div>
							{/each}
						</div>
					</div>
				</div>
			{/each}
		</div>
	{/if}
</div>
	let saving = false;
	let form: ModuleConfig | null = null;

	const unsubscribe = modules.subscribe(($modules) => {
		if ($modules) {
			form = {
				core_payroll: $modules.core_payroll,
				coretax_integration: $modules.coretax_integration,
				compliance_ojk: $modules.compliance_ojk,
				compliance_pdp: $modules.compliance_pdp,
				audit_trail: $modules.audit_trail,
				bpjs_integration: $modules.bpjs_integration,
				syariah_extension: $modules.syariah_extension
			};
		}
	});

	onDestroy(() => {
		unsubscribe?.();
	});

	onMount(async () => {
		try {
			loading = true;
			await modules.load();
		} catch (error) {
			console.error('Failed to load modules:', error);
			toast.error('Gagal memuat konfigurasi modul');
		} finally {
			loading = false;
		}
	});

	function toggleModule(key: keyof ModuleConfig) {
		if (!form) return;
		form = {
			...form,
			[key]: !form[key]
		};
	}

	async function saveChanges() {
		if (!form) return;
		saving = true;
		try {
			await modules.update(form);
		} catch (error) {
			console.error('Failed to update modules:', error);
		} finally {
			saving = false;
		}
	}

	const moduleSections = [
		{
			title: 'Modul Inti',
			description: 'Kontrol fitur utama yang mempengaruhi seluruh aplikasi.',
			items: [
				{
					key: 'core_payroll',
					label: 'Core Payroll',
					description: 'Modul penggajian utama untuk perhitungan PPh 21 dan slip gaji.',
					tag: 'Wajib aktif',
					disabled: true
				},
				{
					key: 'audit_trail',
					label: 'Audit Trail',
					description: 'Aktifkan pencatatan aktivitas untuk setiap perubahan data penting.',
					tag: 'Direkomendasikan'
				}
			]
		},
		{
			title: 'Integrasi Eksternal',
			description: 'Kontrol modul yang berhubungan dengan layanan pihak ketiga.',
			items: [
				{
					key: 'coretax_integration',
					label: 'CoreTax Integration',
					description: 'Generate BPA1/BPA2 dan upload langsung ke CoreTax.',
					tag: 'Perlu akses CoreTax'
				},
				{
					key: 'bpjs_integration',
					label: 'BPJS Integration',
					description: 'Sinkronisasi iuran dan data kepesertaan BPJS.',
					tag: 'Segera hadir'
				}
			]
		},
		{
			title: 'Kepatuhan & Regulasi',
			description: 'Aktifkan modul pendukung regulasi khusus.',
			items: [
				{
					key: 'compliance_ojk',
					label: 'Compliance OJK',
					description: 'Template pelaporan berkala sesuai regulasi OJK.',
					tag: 'Finansial'
				},
				{
					key: 'compliance_pdp',
					label: 'Compliance PDP',
					description: 'Pengaturan privasi dan perlindungan data personal.',
					tag: 'Privasi'
				}
			]
		},
		{
			title: 'Ekstensi Bisnis',
			description: 'Fitur tambahan untuk model bisnis tertentu.',
			items: [
				{
					key: 'syariah_extension',
					label: 'Syariah Extension',
					description: 'Penyesuaian kebijakan payroll untuk entitas berbasis syariah.',
					tag: 'Opsional'
				}
			]
		}
	];
</script>

<div class="space-y-8">
	<div class="flex flex-wrap items-center gap-4 justify-between">
		<div>
			<h1 class="text-3xl font-bold text-base-content">Modul & Feature Flags</h1>
			<p class="text-base-content opacity-80 mt-1">
				Aktifkan atau nonaktifkan modul sesuai kebutuhan tenant. Perubahan akan langsung diterapkan setelah disimpan.
			</p>
		</div>
		<button class="btn btn-success text-white" on:click={saveChanges} disabled={saving || loading || !form}>
			{#if saving}
				<span class="loading loading-spinner loading-sm"></span>
				Menyimpan...
			{:else}
				Simpan Perubahan
			{/if}
		</button>
	</div>

	{#if loading || !form}
		<div class="flex justify-center items-center min-h-[280px]">
			<span class="loading loading-spinner loading-lg text-primary"></span>
		</div>
	{:else}
		<div class="grid gap-6">
			{#each moduleSections as section}
				<div class="card bg-base-100 shadow">
					<div class="card-body space-y-4">
						<div>
							<h2 class="card-title text-base-content">{section.title}</h2>
							<p class="text-base-content opacity-70">{section.description}</p>
						</div>

						<div class="space-y-4">
							{#each section.items as item}
								<div class="flex flex-col md:flex-row md:items-center gap-4 p-4 border border-base-300 rounded-2xl">
									<div class="flex-1">
										<div class="flex items-center gap-2">
											<h3 class="text-base font-semibold text-base-content">{item.label}</h3>
											{#if item.tag}
												<span class="badge badge-ghost badge-sm">{item.tag}</span>
											{/if}
										</div>
										<p class="text-sm text-base-content opacity-70 mt-1">{item.description}</p>
									</div>
									<label class="flex items-center gap-3">
										<span class="text-sm font-semibold text-base-content">{form[item.key] ? 'Aktif' : 'Nonaktif'}</span>
										<input
											type="checkbox"
											class="toggle toggle-success"
											checked={form[item.key]}
											on:change={() => toggleModule(item.key)}
											disabled={item.disabled || saving}
										/>
									</label>
								</div>
							{/each}
						</div>
					</div>
				</div>
			{/each}
		</div>
	{/if}
</div>
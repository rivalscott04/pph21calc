<script lang="ts">
	import { onMount } from 'svelte';
	import { page } from '$app/stores';
	import { goto } from '$app/navigation';
	import { payrollApi, type Period, type Earning, type Deduction } from '$lib/api/payroll.js';
	import { employmentsApi, type Employment } from '$lib/api/employments.js';
	import { componentsApi, type Component } from '$lib/api/components.js';
	import { calculatorApi, type CalculationHistory } from '$lib/api/calculator.js';
	import { toast } from '$lib/stores/toast.js';

	let loading = true;
	let saving = false;
	let periodId: number | null = null;
	let period: Period | null = null;
	let employments: Employment[] = [];
	let components: Component[] = [];
	let earnings: Map<number, Map<number, number>> = new Map(); // employment_id -> component_id -> amount
	let deductions: Map<number, { iuran_pensiun: number; zakat: number; lainnya: number }> = new Map(); // employment_id -> deductions
	let earningsUpdateTrigger = 0; // Trigger for reactivity
	let deductionsUpdateTrigger = 0; // Trigger for reactivity
	let earningsTotals: Map<number, number> = new Map();
	let deductionsTotals: Map<number, number> = new Map();
	let hasEarnings = false;
	let hasDeductions = false;
	let importing = false;
	
	let activeTab: 'earnings' | 'deductions' = 'earnings';
	let searchQuery = '';
	let filteredEmployments: Employment[] = [];

	function formatCurrency(amount: number): string {
		// Ensure amount is a valid number
		const safeAmount = isNaN(amount) || amount === null || amount === undefined ? 0 : amount;
		return new Intl.NumberFormat('id-ID', {
			style: 'currency',
			currency: 'IDR',
			minimumFractionDigits: 0
		}).format(safeAmount);
	}

	function formatNumber(value: number | string | undefined | null): string {
		if (value === null || value === undefined || value === '') return '';
		let num: number;
		if (typeof value === 'string') {
			// Remove all dots and parse as integer to avoid precision loss
			const cleaned = value.replace(/\./g, '').replace(/Rp\s?/gi, '').trim();
			num = parseInt(cleaned, 10);
		} else {
			num = Math.floor(value);
		}
		if (isNaN(num) || num === 0) return '';
		return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
	}

	function parseFormattedNumber(value: string): number {
		if (!value) return 0;
		// Remove all dots (thousand separators) and parse as integer
		const cleaned = value.replace(/\./g, '').replace(/Rp\s?/gi, '').trim();
		const num = parseInt(cleaned, 10);
		return isNaN(num) ? 0 : num;
	}

	function handleAmountInput(employmentId: number, componentId: number, event: Event) {
		const input = event.currentTarget as HTMLInputElement;
		const value = input.value;
		const numValue = parseFormattedNumber(value);
		const formatted = formatNumber(numValue);
		input.value = formatted;
		
		if (!earnings.has(employmentId)) {
			earnings.set(employmentId, new Map());
		}
		// Pastikan disimpan sebagai number integer
		const finalValue = Number(numValue) || 0;
		earnings.get(employmentId)!.set(componentId, finalValue);
		earningsUpdateTrigger++; // Trigger reactivity
	}

	function handleDeductionInput(employmentId: number, type: 'iuran_pensiun' | 'zakat' | 'lainnya', event: Event) {
		const input = event.currentTarget as HTMLInputElement;
		const value = input.value;
		const numValue = parseFormattedNumber(value);
		// Ensure we never store NaN
		const safeValue = isNaN(numValue) ? 0 : numValue;
		const formatted = formatNumber(safeValue);
		input.value = formatted;
		
		if (!deductions.has(employmentId)) {
			deductions.set(employmentId, { iuran_pensiun: 0, zakat: 0, lainnya: 0 });
		}
		deductions.get(employmentId)![type] = safeValue;
		deductionsUpdateTrigger++; // Trigger reactivity
	}

	function getEarningAmount(employmentId: number, componentId: number): string {
		earningsUpdateTrigger; // Read trigger for reactivity
		const empEarnings = earnings.get(employmentId);
		if (!empEarnings) return '';
		const amount = empEarnings.get(componentId) || 0;
		return amount > 0 ? formatNumber(amount) : '';
	}

	function getDeductionAmount(employmentId: number, type: 'iuran_pensiun' | 'zakat' | 'lainnya'): string {
		deductionsUpdateTrigger; // Read trigger for reactivity
		const empDeductions = deductions.get(employmentId);
		if (!empDeductions) return '';
		const amount = empDeductions[type] || 0;
		return amount > 0 ? formatNumber(amount) : '';
	}

	function getTotalEarnings(employmentId: number): number {
		earningsUpdateTrigger; // Read trigger for reactivity
		const empEarnings = earnings.get(employmentId);
		if (!empEarnings) return 0;
		let total = 0;
		empEarnings.forEach((amount) => {
			const numAmount = Number(amount) || 0;
			total += numAmount;
		});
		return isNaN(total) ? 0 : total;
	}

	function getTotalDeductions(employmentId: number): number {
		deductionsUpdateTrigger; // Read trigger for reactivity
		const empDeductions = deductions.get(employmentId);
		if (!empDeductions) return 0;
		const iuran = Number(empDeductions.iuran_pensiun) || 0;
		const zakat = Number(empDeductions.zakat) || 0;
		const lainnya = Number(empDeductions.lainnya) || 0;
		const total = iuran + zakat + lainnya;
		return isNaN(total) ? 0 : total;
	}

	function hasEarningsData(): boolean {
		earningsUpdateTrigger; // Read trigger for reactivity
		let hasData = false;
		earnings.forEach((empEarnings) => {
			empEarnings.forEach((amount) => {
				if (amount > 0) {
					hasData = true;
				}
			});
		});
		return hasData;
	}

	function hasDeductionsData(): boolean {
		deductionsUpdateTrigger; // Read trigger for reactivity
		let hasData = false;
		deductions.forEach((empDeductions) => {
			if ((empDeductions.iuran_pensiun || 0) > 0 || 
			    (empDeductions.zakat || 0) > 0 || 
			    (empDeductions.lainnya || 0) > 0) {
				hasData = true;
			}
		});
		return hasData;
	}

	// Reactive validation - update when trigger changes
	$: {
		earningsUpdateTrigger; // Read trigger to make reactive
		hasEarnings = hasEarningsData();
	}
	
	$: {
		deductionsUpdateTrigger; // Read trigger to make reactive
		hasDeductions = hasDeductionsData();
	}

	// Reactive totals - recalculate when trigger changes
	$: {
		earningsUpdateTrigger; // Read trigger to make reactive
		const totals = new Map<number, number>();
		employments.forEach(emp => {
			const total = getTotalEarnings(emp.id);
			totals.set(emp.id, Number(total) || 0);
		});
		earningsTotals = totals;
	}

	$: {
		deductionsUpdateTrigger; // Read trigger to make reactive
		const totals = new Map<number, number>();
		employments.forEach(emp => {
			const total = getTotalDeductions(emp.id);
			totals.set(emp.id, Number(total) || 0);
		});
		deductionsTotals = totals;
	}

	$: {
		if (searchQuery.trim() === '') {
			filteredEmployments = employments;
		} else {
			const query = searchQuery.toLowerCase();
			filteredEmployments = employments.filter(emp => 
				emp.person?.full_name?.toLowerCase().includes(query) ||
				emp.orgUnit?.name?.toLowerCase().includes(query)
			);
		}
	}

	async function loadPeriod() {
		if (!periodId) return;
		try {
			const response = await payrollApi.listPeriods({ per_page: 100 });
			const found = response.data?.find(p => p.id === periodId);
			if (!found) {
				toast.error('Periode tidak ditemukan');
				goto('/payroll');
				return;
			}
			period = found;
			
			if (period.status === 'posted') {
				toast.error('Periode sudah diposting, tidak dapat diubah');
				goto('/payroll');
				return;
			}
		} catch (error) {
			console.error('Failed to load period:', error);
			toast.error('Gagal memuat periode');
			goto('/payroll');
		}
	}

	async function loadEmployments() {
		try {
			const response = await employmentsApi.list({ 
				active: true,
				primary_payroll: true,
				per_page: 1000 
			});
			employments = response.data || [];
		} catch (error) {
			console.error('Failed to load employments:', error);
			toast.error('Gagal memuat daftar pegawai');
		}
	}

	async function loadComponents() {
		try {
			const response = await componentsApi.list({ per_page: 1000 });
			components = response.data || [];
			if (components.length === 0) {
				toast.warning('Belum ada komponen earnings. Silakan buat komponen terlebih dahulu.');
			}
		} catch (error) {
			console.error('Failed to load components:', error);
			toast.error('Gagal memuat daftar komponen');
			components = [];
		}
	}

	async function loadExistingData() {
		if (!periodId) return;
		try {
			// Load existing earnings
			const earningsResponse = await payrollApi.listEarnings({ 
				period: periodId,
				per_page: 10000 
			});
			const existingEarnings = earningsResponse.data || [];
			
			existingEarnings.forEach((earning) => {
				if (!earnings.has(earning.employment_id)) {
					earnings.set(earning.employment_id, new Map());
				}
				// Pastikan amount adalah integer, bukan decimal
				// Backend mengembalikan decimal dengan 2 desimal, jadi pastikan di-parse sebagai integer
				const amount = Math.floor(Number(earning.amount) || 0);
				earnings.get(earning.employment_id)!.set(earning.component_id, amount);
			});

			// Load existing deductions
			const deductionsResponse = await payrollApi.listDeductions({ 
				period: periodId,
				per_page: 10000 
			});
			const existingDeductions = deductionsResponse.data || [];
			
			existingDeductions.forEach((deduction) => {
				if (!deductions.has(deduction.employment_id)) {
					deductions.set(deduction.employment_id, { iuran_pensiun: 0, zakat: 0, lainnya: 0 });
				}
				const empDeductions = deductions.get(deduction.employment_id)!;
				empDeductions[deduction.type] = (empDeductions[deduction.type] || 0) + deduction.amount;
			});
			
			earningsUpdateTrigger++;
			deductionsUpdateTrigger++;
		} catch (error) {
			console.error('Failed to load existing data:', error);
			toast.error('Gagal memuat data existing');
		}
	}

	async function importFromCalculator() {
		if (!periodId || !period) return;
		
		importing = true;
		try {
			// Get calculation history for the same period
			const historyResponse = await calculatorApi.getHistory({
				year: period.year,
				month: period.month,
				per_page: 10000
			});
			
			const histories = historyResponse.data || [];
			
			if (histories.length === 0) {
				toast.warning('Tidak ada data dari kalkulator untuk periode ini');
				importing = false;
				return;
			}

			// Find default component for bruto (first taxable component or "Gaji Pokok")
			let defaultComponent: Component | null = null;
			const gajiPokokComponent = components.find(c => c.group === 'gaji_pokok' && c.taxable);
			if (gajiPokokComponent) {
				defaultComponent = gajiPokokComponent;
			} else {
				// Fallback to first taxable component
				defaultComponent = components.find(c => c.taxable) || components[0] || null;
			}

			if (!defaultComponent) {
				toast.error('Tidak ada komponen earnings yang tersedia. Silakan buat komponen terlebih dahulu.');
				importing = false;
				return;
			}

			let importedCount = 0;
			let skippedCount = 0;

			// Import earnings (bruto) and deductions
			histories.forEach((history) => {
				if (!history.employment_id) {
					skippedCount++;
					return;
				}

				// Find employment - cek dengan benar
				const employment = employments.find(emp => emp.id === history.employment_id);
				if (!employment) {
					skippedCount++;
					return;
				}

				// Import bruto as earnings - mapping ke Gaji Pokok
				// Data di history dari kalkulator biasanya annual, jadi selalu bagi 12 untuk dapat bulanan
				if (history.bruto > 0) {
					if (!earnings.has(history.employment_id)) {
						earnings.set(history.employment_id, new Map());
					}
					// Cek apakah sudah ada data untuk komponen ini
					const existingAmount = earnings.get(history.employment_id)?.get(defaultComponent.id) || 0;
					// Hanya import jika belum ada data (existingAmount === 0)
					if (existingAmount === 0) {
						let brutoValue = Number(history.bruto) || 0;
						
						// Cek apakah bruto adalah annual (dari kalkulator standalone biasanya > 50jt)
						// Jika bruto > 50jt, pasti annual, bagi 12 untuk dapat bulanan
						// Jika bruto <= 50jt, kemungkinan sudah bulanan, tidak perlu bagi 12
						if (brutoValue > 50000000) {
							// Annual, bagi 12 untuk dapat bulanan
							brutoValue = Math.floor(brutoValue / 12);
						}
						// Jika bruto <= 50jt, sudah bulanan, langsung pakai
						
						brutoValue = Math.floor(brutoValue);
						if (brutoValue > 0) {
							earnings.get(history.employment_id)!.set(defaultComponent.id, brutoValue);
						}
					}
				}

				// Import deductions
				// Data di history dari kalkulator biasanya annual, jadi selalu bagi 12 untuk dapat bulanan
				if (history.iuran_pensiun > 0 || history.zakat > 0 || history.biaya_jabatan > 0) {
					if (!deductions.has(history.employment_id)) {
						deductions.set(history.employment_id, { iuran_pensiun: 0, zakat: 0, lainnya: 0 });
					}
					const empDeductions = deductions.get(history.employment_id)!;
					
					// Cek apakah bruto adalah annual (jika bruto > 50jt, pasti annual)
					const brutoValue = Number(history.bruto) || 0;
					const isAnnual = brutoValue > 50000000;
					
					// Hanya import jika belum ada data
					if (empDeductions.iuran_pensiun === 0 && history.iuran_pensiun > 0) {
						let value = Number(history.iuran_pensiun) || 0;
						// Jika annual, bagi 12 untuk dapat bulanan
						if (isAnnual && value > 0) {
							value = Math.floor(value / 12);
						}
						// Safety check: jika value masih terlalu besar dibanding bruto bulanan
						// Iuran pensiun biasanya < 5% dari bruto, jadi jika > 10% kemungkinan salah format
						const brutoMonthly = isAnnual ? Math.floor(brutoValue / 12) : brutoValue;
						if (value > brutoMonthly * 0.1) {
							// Terlalu besar, kemungkinan masih annual atau salah format, bagi 12 lagi
							value = Math.floor(value / 12);
						}
						empDeductions.iuran_pensiun = Math.floor(value);
					}
					if (empDeductions.zakat === 0 && history.zakat > 0) {
						let value = Number(history.zakat) || 0;
						// Jika annual, bagi 12 untuk dapat bulanan
						if (isAnnual && value > 0) {
							value = Math.floor(value / 12);
						}
						// Safety check
						const brutoMonthly = isAnnual ? Math.floor(brutoValue / 12) : brutoValue;
						if (value > brutoMonthly * 0.1) {
							value = Math.floor(value / 12);
						}
						empDeductions.zakat = Math.floor(value);
					}
					// Biaya jabatan diimport ke "lainnya"
					if (empDeductions.lainnya === 0 && history.biaya_jabatan > 0) {
						let value = Number(history.biaya_jabatan) || 0;
						// Jika annual, bagi 12 untuk dapat bulanan
						if (isAnnual && value > 0) {
							value = Math.floor(value / 12);
						}
						// Safety check: biaya jabatan biasanya 5% dari bruto (max 500rb/bulan atau 6jt/tahun)
						const brutoMonthly = isAnnual ? Math.floor(brutoValue / 12) : brutoValue;
						if (value > Math.min(brutoMonthly * 0.05, 500000)) {
							// Terlalu besar, kemungkinan masih annual atau salah format, bagi 12 lagi
							value = Math.floor(value / 12);
						}
						empDeductions.lainnya = Math.floor(value);
					}
				}

				importedCount++;
			});

			earningsUpdateTrigger++;
			deductionsUpdateTrigger++;

			if (importedCount > 0) {
				toast.success(`Berhasil import ${importedCount} data dari kalkulator${skippedCount > 0 ? ` (${skippedCount} dilewati)` : ''}`);
			} else {
				toast.warning('Tidak ada data yang bisa diimport');
			}
		} catch (error: any) {
			console.error('Failed to import from calculator:', error);
			toast.error(error.message || 'Gagal import dari kalkulator');
		} finally {
			importing = false;
		}
	}

	async function saveEarnings() {
		if (!periodId) return;
		
		saving = true;
		try {
			const earningsData: Array<{
				employment_id: number;
				component_id: number;
				amount: number;
			}> = [];

			earnings.forEach((empEarnings, employmentId) => {
				empEarnings.forEach((amount, componentId) => {
					if (amount > 0) {
						// Pastikan amount adalah integer, bukan decimal
						const finalAmount = Math.floor(Number(amount) || 0);
						earningsData.push({
							employment_id: employmentId,
							component_id: componentId,
							amount: finalAmount
						});
					}
				});
			});

			if (earningsData.length === 0) {
				toast.warning('Tidak ada data earnings untuk disimpan');
				saving = false;
				return;
			}

			const result = await payrollApi.storeEarnings({
				period_id: periodId,
				earnings: earningsData
			});

			// Reload existing data setelah save untuk memastikan data ter-update dengan benar
			await loadExistingData();

			toast.success(`Earnings berhasil disimpan (${result.created} dibuat, ${result.updated} diupdate)`);
		} catch (error: any) {
			console.error('Failed to save earnings:', error);
			toast.error(error.message || 'Gagal menyimpan earnings');
		} finally {
			saving = false;
		}
	}

	async function saveDeductions() {
		if (!periodId) return;
		
		saving = true;
		try {
			const deductionsData: Array<{
				employment_id: number;
				type: 'iuran_pensiun' | 'zakat' | 'lainnya';
				amount: number;
			}> = [];

			deductions.forEach((empDeductions, employmentId) => {
				if (empDeductions.iuran_pensiun > 0) {
					deductionsData.push({
						employment_id: employmentId,
						type: 'iuran_pensiun',
						amount: empDeductions.iuran_pensiun
					});
				}
				if (empDeductions.zakat > 0) {
					deductionsData.push({
						employment_id: employmentId,
						type: 'zakat',
						amount: empDeductions.zakat
					});
				}
				if (empDeductions.lainnya > 0) {
					deductionsData.push({
						employment_id: employmentId,
						type: 'lainnya',
						amount: empDeductions.lainnya
					});
				}
			});

			if (deductionsData.length === 0) {
				toast.warning('Tidak ada data deductions untuk disimpan');
				saving = false;
				return;
			}

			const result = await payrollApi.storeDeductions({
				period_id: periodId,
				deductions: deductionsData
			});

			// Trigger recalculation of totals
			deductionsUpdateTrigger++;
			
			toast.success(`Deductions berhasil disimpan (${result.created} dibuat, ${result.updated} diupdate)`);
		} catch (error: any) {
			console.error('Failed to save deductions:', error);
			toast.error(error.message || 'Gagal menyimpan deductions');
		} finally {
			saving = false;
		}
	}

	onMount(async () => {
		const periodParam = $page.url.searchParams.get('period');
		if (!periodParam) {
			toast.error('Parameter periode tidak ditemukan');
			goto('/payroll');
			return;
		}

		periodId = parseInt(periodParam);
		if (isNaN(periodId)) {
			toast.error('Parameter periode tidak valid');
			goto('/payroll');
			return;
		}

		loading = true;
		try {
			await Promise.all([
				loadPeriod(),
				loadEmployments(),
				loadComponents()
			]);
			await loadExistingData();
		} catch (error) {
			console.error('Failed to initialize:', error);
		} finally {
			loading = false;
		}
	});
</script>

<div class="space-y-6">
	<!-- Header -->
	<div class="flex justify-between items-center">
		<div>
			<h1 class="text-3xl font-bold text-base-content">Input Data Payroll</h1>
			<p class="text-base-content opacity-70 mt-1">
				{#if period}
					Periode: {period.year}-{String(period.month).padStart(2, '0')} 
					<span class="badge badge-sm {period.status === 'draft' ? 'badge-neutral' : period.status === 'reviewed' ? 'badge-info' : period.status === 'approved' ? 'badge-success' : 'badge-primary'}">
						{period.status === 'draft' ? 'Draft' : period.status === 'reviewed' ? 'Direview' : period.status === 'approved' ? 'Disetujui' : 'Diposting'}
					</span>
				{:else}
					Memuat...
				{/if}
			</p>
		</div>
		<a href="/payroll" class="btn btn-ghost">
			<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
			</svg>
			Kembali
		</a>
	</div>

	{#if loading}
		<div class="flex justify-center items-center min-h-[400px]">
			<span class="loading loading-spinner loading-lg"></span>
		</div>
	{:else if period && period.status === 'posted'}
		<div class="alert alert-warning">
			<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
			</svg>
			<span>Periode sudah diposting, tidak dapat diubah</span>
		</div>
	{:else}
		<!-- Tabs -->
		<div class="tabs tabs-box">
			<button 
				class="tab text-base-content {activeTab === 'earnings' ? 'tab-active text-base-content' : ''}"
				on:click={() => activeTab = 'earnings'}
			>
				Earnings
			</button>
			<button 
				class="tab text-base-content {activeTab === 'deductions' ? 'tab-active text-base-content' : ''}"
				on:click={() => activeTab = 'deductions'}
			>
				Deductions
			</button>
		</div>

		<!-- Search -->
		<div class="form-control">
			<input 
				type="text" 
				placeholder="Cari pegawai..." 
				class="input input-bordered text-base-content placeholder:text-base-content/60"
				bind:value={searchQuery}
			/>
		</div>

		<!-- Earnings Tab -->
		{#if activeTab === 'earnings'}
			<div class="card bg-base-100 shadow-lg">
				<div class="card-body">
					<div class="flex justify-between items-center mb-4">
						<h2 class="card-title text-base-content">Input Earnings</h2>
						<div class="flex gap-2">
						<button 
							class="btn btn-outline btn-neutral text-base-content hover:text-white"
							on:click={importFromCalculator}
							disabled={importing || !period}
						>
								{#if importing}
									<span class="loading loading-spinner loading-sm"></span>
									Importing...
								{:else}
									<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
									</svg>
									Import dari Kalkulator
								{/if}
							</button>
							<button 
								class="btn btn-primary text-white"
								on:click={saveEarnings}
								disabled={saving || !hasEarnings}
							>
								{#if saving}
									<span class="loading loading-spinner loading-sm"></span>
									Menyimpan...
								{:else}
									<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
									</svg>
									Simpan Earnings
								{/if}
							</button>
						</div>
					</div>

					{#if components.length === 0}
						<div class="alert alert-warning">
							<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
							</svg>
							<span>Belum ada komponen earnings. Silakan buat komponen terlebih dahulu di menu Master Data.</span>
						</div>
					{:else}
						<div class="overflow-x-auto">
							<table class="table table-zebra">
								<thead>
									<tr>
										<th class="text-base-content">Nama Pegawai</th>
										<th class="text-base-content">Unit Organisasi</th>
										{#each components as component}
											<th class="text-base-content text-right">{component.name}</th>
										{/each}
										<th class="text-base-content text-right">Total</th>
									</tr>
								</thead>
								<tbody>
									{#each filteredEmployments as employment}
										<tr>
											<td class="text-base-content">{employment.person?.full_name || '-'}</td>
											<td class="text-base-content">{employment.orgUnit?.name || '-'}</td>
											{#each components as component}
												<td class="text-right">
													<input 
														type="text" 
														class="input input-sm input-bordered text-right text-base-content w-full"
														placeholder="0"
														value={getEarningAmount(employment.id, component.id)}
														on:input={(e) => handleAmountInput(employment.id, component.id, e)}
													/>
												</td>
											{/each}
											<td class="text-right font-semibold text-base-content">
												{formatCurrency(earningsTotals.get(employment.id) || 0)}
											</td>
										</tr>
									{/each}
								</tbody>
							</table>
						</div>
					{/if}
				</div>
			</div>
		{/if}

		<!-- Deductions Tab -->
		{#if activeTab === 'deductions'}
			<div class="card bg-base-100 shadow-lg">
				<div class="card-body">
					<div class="flex justify-between items-center mb-4">
						<h2 class="card-title text-base-content">Input Deductions</h2>
						<button 
							class="btn btn-primary text-white"
							on:click={saveDeductions}
							disabled={saving || !hasDeductions}
						>
							{#if saving}
								<span class="loading loading-spinner loading-sm"></span>
								Menyimpan...
							{:else}
								<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
								</svg>
								Simpan Deductions
							{/if}
						</button>
					</div>

					<div class="overflow-x-auto">
						<table class="table table-zebra">
							<thead>
								<tr>
									<th class="text-base-content">Nama Pegawai</th>
									<th class="text-base-content">Unit Organisasi</th>
									<th class="text-base-content text-right">Iuran Pensiun</th>
									<th class="text-base-content text-right">Zakat</th>
									<th class="text-base-content text-right">Biaya Jabatan</th>
									<th class="text-base-content text-right">Total</th>
								</tr>
							</thead>
							<tbody>
								{#each filteredEmployments as employment}
									<tr>
										<td class="text-base-content">{employment.person?.full_name || '-'}</td>
										<td class="text-base-content">{employment.orgUnit?.name || '-'}</td>
										<td class="text-right">
											<input 
												type="text" 
												class="input input-sm input-bordered text-right text-base-content w-full"
												placeholder="0"
												value={getDeductionAmount(employment.id, 'iuran_pensiun')}
												on:input={(e) => handleDeductionInput(employment.id, 'iuran_pensiun', e)}
											/>
										</td>
										<td class="text-right">
											<input 
												type="text" 
												class="input input-sm input-bordered text-right text-base-content w-full"
												placeholder="0"
												value={getDeductionAmount(employment.id, 'zakat')}
												on:input={(e) => handleDeductionInput(employment.id, 'zakat', e)}
											/>
										</td>
										<td class="text-right">
											<input 
												type="text" 
												class="input input-sm input-bordered text-right text-base-content w-full"
												placeholder="0"
												value={getDeductionAmount(employment.id, 'lainnya')}
												on:input={(e) => handleDeductionInput(employment.id, 'lainnya', e)}
											/>
										</td>
											<td class="text-right font-semibold text-base-content">
												{formatCurrency(deductionsTotals.get(employment.id) || 0)}
											</td>
									</tr>
								{/each}
							</tbody>
						</table>
					</div>
				</div>
			</div>
		{/if}
	{/if}
</div>


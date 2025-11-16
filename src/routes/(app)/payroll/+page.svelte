<script lang="ts">
	import { onMount } from 'svelte';
	import { payrollApi, type Period, type Earning, type Deduction, type PayrollSummary } from '$lib/api/payroll.js';
	import { employmentsApi, type Employment } from '$lib/api/employments.js';
	import { toast } from '$lib/stores/toast.js';

	let loading = true;
	let periods: Period[] = [];
	let selectedPeriod: Period | null = null;
	let periodSummary: PayrollSummary | null = null;
	let previewData: any = null;
	let showPreview = false;
	let previewLoading = false;
	let commitLoading = false;
	
	// Pagination for calculations table
	let currentPage = 1;
	const itemsPerPage = 20;

	const currentYear = new Date().getFullYear();
	const currentMonth = new Date().getMonth() + 1;

	function formatCurrency(amount: number): string {
		return new Intl.NumberFormat('id-ID', {
			style: 'currency',
			currency: 'IDR',
			minimumFractionDigits: 0
		}).format(amount);
	}

	function getStatusBadgeClass(status: string): string {
		const statusMap: Record<string, string> = {
			draft: 'badge-neutral',
			reviewed: 'badge-info',
			approved: 'badge-success',
			posted: 'badge-primary'
		};
		return statusMap[status] || 'badge-neutral';
	}

	function getStatusLabel(status: string): string {
		const statusMap: Record<string, string> = {
			draft: 'Draft',
			reviewed: 'Direview',
			approved: 'Disetujui',
			posted: 'Diposting'
		};
		return statusMap[status] || status;
	}

	async function loadPeriods() {
		try {
			loading = true;
			const response = await payrollApi.listPeriods({ per_page: 50 });
			periods = response.data || [];
			if (periods.length > 0 && !selectedPeriod) {
				selectedPeriod = periods[0];
				await loadPeriodSummary();
			}
		} catch (error) {
			console.error('Failed to load periods:', error);
			toast.error('Gagal memuat daftar periode');
		} finally {
			loading = false;
		}
	}

	async function loadPeriodSummary() {
		if (!selectedPeriod) return;
		try {
			periodSummary = await payrollApi.summary(selectedPeriod.id);
		} catch (error) {
			console.error('Failed to load period summary:', error);
			toast.error('Gagal memuat ringkasan periode');
		}
	}

	async function createPeriod() {
		try {
			const newPeriod = await payrollApi.createPeriod({
				year: currentYear,
				month: currentMonth
			});
			toast.success('Periode berhasil dibuat');
			await loadPeriods();
			selectedPeriod = newPeriod;
			await loadPeriodSummary();
		} catch (error: any) {
			console.error('Failed to create period:', error);
			toast.error(error.message || 'Gagal membuat periode');
		}
	}

	async function updatePeriodStatus(status: 'draft' | 'reviewed' | 'approved' | 'posted') {
		if (!selectedPeriod) return;
		try {
			await payrollApi.updatePeriodStatus(selectedPeriod.id, status);
			toast.success(`Status periode berhasil diubah menjadi ${getStatusLabel(status)}`);
			await loadPeriods();
			await loadPeriodSummary();
		} catch (error: any) {
			console.error('Failed to update period status:', error);
			toast.error(error.message || 'Gagal mengubah status periode');
		}
	}

	async function previewPayroll() {
		if (!selectedPeriod) return;
		previewLoading = true;
		previewData = null;
		try {
			previewData = await payrollApi.preview(selectedPeriod.id);
			showPreview = true;
		} catch (error) {
			console.error('Failed to preview payroll:', error);
			toast.error('Gagal melakukan preview payroll');
		} finally {
			previewLoading = false;
		}
	}

	async function commitPayroll() {
		if (!selectedPeriod) return;
		if (!confirm('Apakah Anda yakin ingin commit payroll? Tindakan ini tidak dapat dibatalkan.')) {
			return;
		}
		commitLoading = true;
		try {
			await payrollApi.commit(selectedPeriod.id);
			toast.success('Payroll berhasil di-commit');
			showPreview = false;
			previewData = null;
			await loadPeriods();
			await loadPeriodSummary();
		} catch (error: any) {
			console.error('Failed to commit payroll:', error);
			toast.error(error.message || 'Gagal commit payroll');
		} finally {
			commitLoading = false;
		}
	}

	$: if (selectedPeriod) {
		loadPeriodSummary();
		currentPage = 1; // Reset to first page when period changes
	}

	// Pagination calculations
	$: totalPages = periodSummary && periodSummary.calculations 
		? Math.ceil(periodSummary.calculations.length / itemsPerPage) 
		: 0;
	
	$: paginatedCalculations = periodSummary && periodSummary.calculations
		? periodSummary.calculations.slice(
			(currentPage - 1) * itemsPerPage,
			currentPage * itemsPerPage
		)
		: [];
	
	$: startItem = periodSummary && periodSummary.calculations
		? (currentPage - 1) * itemsPerPage + 1
		: 0;
	
	$: endItem = periodSummary && periodSummary.calculations
		? Math.min(currentPage * itemsPerPage, periodSummary.calculations.length)
		: 0;

	onMount(async () => {
		await loadPeriods();
	});
</script>

<div class="space-y-6">
	<div class="flex justify-between items-center">
		<div>
			<h1 class="text-3xl font-bold text-base-content">Payroll</h1>
			<p class="text-base-content opacity-70 mt-1">Kelola payroll dan perhitungan PPh 21</p>
		</div>
		<button class="btn btn-success text-white" on:click={createPeriod}>
			<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
			</svg>
			Tambah Periode
		</button>
	</div>

	{#if loading}
		<div class="flex justify-center items-center min-h-[400px]">
			<span class="loading loading-spinner loading-lg text-primary"></span>
		</div>
	{:else}
		<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
			<!-- Period List -->
			<div class="lg:col-span-1">
				<div class="card bg-base-100 shadow-lg">
					<div class="card-body p-4">
						<h2 class="card-title text-base-content text-lg mb-4 pb-2 border-b border-base-300">Daftar Periode</h2>
						<div class="space-y-2 max-h-[600px] overflow-y-auto">
							{#each periods as period}
								<button
									class={`w-full text-left p-3 rounded-lg transition-all ${
										selectedPeriod?.id === period.id 
											? 'bg-primary text-primary-content shadow-md' 
											: 'bg-base-200 text-base-content hover:bg-base-300 hover:text-white'
									}`}
									on:click={() => {
										selectedPeriod = period;
										showPreview = false;
										previewData = null;
									}}
								>
									<div class="flex items-center justify-between gap-2">
										<div class="flex-1 min-w-0">
											<div class="font-semibold text-sm truncate">
												{new Date(period.year, period.month - 1).toLocaleString('id-ID', {
													month: 'long',
													year: 'numeric'
												})}
											</div>
										</div>
										<span class={`badge badge-sm shrink-0 ${
											selectedPeriod?.id === period.id 
												? 'bg-primary-content text-primary border border-primary-content/20' 
												: getStatusBadgeClass(period.status)
										}`}>
											{getStatusLabel(period.status)}
										</span>
									</div>
								</button>
							{/each}
							{#if periods.length === 0}
								<div class="text-center py-8 text-base-content opacity-50">
									<p>Belum ada periode</p>
									<button class="btn btn-sm btn-primary text-white mt-4" on:click={createPeriod}>
										Buat Periode Pertama
									</button>
								</div>
							{/if}
						</div>
					</div>
				</div>
			</div>

			<!-- Period Detail -->
			<div class="lg:col-span-2">
				{#if selectedPeriod}
					<div class="space-y-8">
						<!-- Period Header with Primary Action -->
						<div class="card bg-base-100 shadow-lg">
							<div class="card-body">
								<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
									<div class="flex-1">
										<h1 class="text-4xl font-bold text-base-content mb-2">
											{new Date(selectedPeriod.year, selectedPeriod.month - 1).toLocaleString('id-ID', {
												month: 'long',
												year: 'numeric'
											})}
										</h1>
										<div class="flex items-center gap-3">
											<span class="badge {getStatusBadgeClass(selectedPeriod.status)} badge-lg text-sm px-4 py-2">
												{getStatusLabel(selectedPeriod.status)}
											</span>
										</div>
									</div>
									<div class="flex flex-col sm:flex-row gap-2">
										{#if selectedPeriod.status !== 'posted'}
											<a
												href="/payroll/input?period={selectedPeriod.id}"
												class="btn btn-primary btn-lg text-white shadow-md hover:shadow-lg transition-shadow"
											>
												<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
													<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
												</svg>
												Input Data
											</a>
										{/if}
										<details class="dropdown dropdown-end">
											<summary class="btn btn-outline btn-neutral btn-lg text-base-content hover:text-white">
												<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
													<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
												</svg>
												Lainnya
											</summary>
											<ul class="dropdown-content menu bg-base-100 rounded-box shadow-lg border border-base-300 w-56 mt-2 z-10">
												{#if selectedPeriod.status !== 'posted'}
													<li>
														<button
															class="text-base-content hover:text-white"
															on:click={previewPayroll}
															disabled={previewLoading}
														>
															{#if previewLoading}
																<span class="loading loading-spinner loading-sm"></span>
															{:else}
																<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
																	<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
																	<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
																</svg>
															{/if}
															Preview Payroll
														</button>
													</li>
												{/if}
												{#if selectedPeriod.status === 'approved'}
													<li>
														<button
															class="text-base-content hover:text-white"
															on:click={commitPayroll}
															disabled={commitLoading}
														>
															{#if commitLoading}
																<span class="loading loading-spinner loading-sm"></span>
															{:else}
																<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
																	<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
																</svg>
															{/if}
															Commit Payroll
														</button>
													</li>
												{/if}
												{#if selectedPeriod.status === 'draft'}
													<li>
														<button
															class="text-base-content hover:text-white"
															on:click={() => updatePeriodStatus('reviewed')}
														>
															<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
																<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
															</svg>
															Setujui Review
														</button>
													</li>
												{/if}
												{#if selectedPeriod.status === 'reviewed'}
													<li>
														<button
															class="text-base-content hover:text-white"
															on:click={() => updatePeriodStatus('approved')}
														>
															<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
																<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
															</svg>
															Setujui
														</button>
													</li>
												{/if}
												{#if selectedPeriod.status === 'approved'}
													<li>
														<button
															class="text-base-content hover:text-white"
															on:click={() => updatePeriodStatus('posted')}
														>
															<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
																<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
															</svg>
															Posting
														</button>
													</li>
												{/if}
											</ul>
										</details>
									</div>
								</div>
							</div>
						</div>

						<!-- Summary Cards with Icons -->
						{#if periodSummary}
							<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
								<div class="stat bg-base-200 rounded-lg shadow hover:shadow-md transition-shadow overflow-hidden relative">
									<div class="stat-figure text-primary opacity-20 absolute top-2 right-2">
										<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="w-10 h-10 stroke-current">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
										</svg>
									</div>
									<div class="stat-title text-base-content opacity-70 text-sm">Total Karyawan</div>
									<div class="stat-value text-primary text-3xl">{periodSummary.total_employees}</div>
								</div>
								<div class="stat bg-base-200 rounded-lg shadow hover:shadow-md transition-shadow overflow-hidden relative">
									<div class="stat-figure text-secondary opacity-20 absolute top-2 right-2">
										<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="w-10 h-10 stroke-current">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
										</svg>
									</div>
									<div class="stat-title text-base-content opacity-70 text-sm">Total Bruto</div>
									<div class="stat-value text-secondary text-2xl">{formatCurrency(periodSummary.total_bruto)}</div>
								</div>
								<div class="stat bg-base-200 rounded-lg shadow hover:shadow-md transition-shadow overflow-hidden relative">
									<div class="stat-figure text-accent opacity-20 absolute top-2 right-2">
										<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="w-10 h-10 stroke-current">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
										</svg>
									</div>
									<div class="stat-title text-base-content opacity-70 text-sm">Total Neto</div>
									<div class="stat-value text-accent text-2xl">{formatCurrency(periodSummary.total_neto)}</div>
								</div>
								<div class="stat bg-base-200 rounded-lg shadow hover:shadow-md transition-shadow overflow-hidden relative">
									<div class="stat-figure text-success opacity-20 absolute top-2 right-2">
										<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="w-10 h-10 stroke-current">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
										</svg>
									</div>
									<div class="stat-title text-base-content opacity-70 text-sm">Total PPh 21</div>
									<div class="stat-value text-success text-2xl">{formatCurrency(periodSummary.total_pph21)}</div>
								</div>
							</div>
						{/if}

						<!-- Preview Modal -->
						{#if showPreview && previewData}
							<div class="card bg-base-100 shadow-lg border-2 border-primary">
								<div class="card-body">
									<div class="flex justify-between items-center mb-6">
										<div>
											<h3 class="card-title text-base-content text-xl">Preview Payroll</h3>
											<p class="text-base-content opacity-70 text-sm mt-1">{previewData.period}</p>
										</div>
										<button class="btn btn-sm btn-ghost btn-circle hover:text-white" on:click={() => (showPreview = false)} aria-label="Tutup preview">
											<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
											</svg>
										</button>
									</div>
									<div class="overflow-x-auto">
										<table class="table table-zebra">
											<thead>
												<tr class="bg-base-200">
													<th class="text-base-content font-semibold">Nama</th>
													<th class="text-base-content text-right font-semibold">Bruto</th>
													<th class="text-base-content text-right font-semibold">Neto</th>
													<th class="text-base-content text-right font-semibold">PPh 21</th>
												</tr>
											</thead>
											<tbody>
												{#each previewData.previews as preview}
													<tr class="hover:bg-base-200 transition-colors">
														<td class="text-base-content font-medium">{preview.person_name}</td>
														<td class="text-base-content text-right">{formatCurrency(preview.bruto)}</td>
														<td class="text-base-content text-right">{formatCurrency(preview.neto_masa)}</td>
														<td class="text-base-content text-right font-semibold text-primary">
															{formatCurrency(preview.pph21_masa)}
														</td>
													</tr>
												{/each}
											</tbody>
										</table>
									</div>
								</div>
							</div>
						{/if}

						<!-- Calculations List -->
						{#if periodSummary && periodSummary.calculations.length > 0}
							<div class="card bg-base-100 shadow-lg">
								<div class="card-body">
									<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
										<h3 class="card-title text-base-content text-xl">Detail Perhitungan</h3>
										<div class="flex items-center gap-3">
											<span class="badge badge-neutral badge-lg">
												{periodSummary.calculations.length} Karyawan
											</span>
											{#if periodSummary.calculations.length > itemsPerPage}
												<span class="text-sm text-base-content opacity-70">
													Menampilkan {startItem}-{endItem} dari {periodSummary.calculations.length}
												</span>
											{/if}
										</div>
									</div>
									<div class="overflow-x-auto">
										<table class="table table-zebra">
											<thead>
												<tr class="bg-base-200">
													<th class="text-base-content font-semibold">Nama</th>
													<th class="text-base-content font-semibold">Unit</th>
													<th class="text-base-content text-right font-semibold">Bruto</th>
													<th class="text-base-content text-right font-semibold">Neto</th>
													<th class="text-base-content text-right font-semibold">PPh 21</th>
												</tr>
											</thead>
											<tbody>
												{#each paginatedCalculations as calc}
													<tr class="hover:bg-base-200 transition-colors">
														<td class="text-base-content font-medium">{calc.person_name}</td>
														<td class="text-base-content opacity-70">{calc.org_unit}</td>
														<td class="text-base-content text-right">{formatCurrency(calc.bruto)}</td>
														<td class="text-base-content text-right">{formatCurrency(calc.neto_masa)}</td>
														<td class="text-base-content text-right font-semibold text-primary">
															{formatCurrency(calc.pph21_masa)}
														</td>
													</tr>
												{/each}
											</tbody>
											<tfoot class="bg-base-200">
												<tr>
													<th class="text-base-content font-bold" colspan="2">Total</th>
													<th class="text-base-content text-right font-bold">
														{formatCurrency(periodSummary.total_bruto)}
													</th>
													<th class="text-base-content text-right font-bold">
														{formatCurrency(periodSummary.total_neto)}
													</th>
													<th class="text-base-content text-right font-bold text-primary">
														{formatCurrency(periodSummary.total_pph21)}
													</th>
												</tr>
											</tfoot>
										</table>
									</div>
									{#if periodSummary.calculations.length > itemsPerPage}
										<div class="flex flex-col sm:flex-row items-center justify-center gap-3 mt-6">
											<button
												class="btn btn-sm btn-ghost hover:text-white"
												on:click={() => currentPage = Math.max(1, currentPage - 1)}
												disabled={currentPage === 1}
											>
												<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
													<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
												</svg>
												Sebelumnya
											</button>
											<div class="join">
												{#if totalPages <= 7}
													{#each Array(totalPages) as _, i}
														{@const pageNum = i + 1}
														<button
															class="join-item btn btn-sm {currentPage === pageNum ? 'btn-active' : 'btn-ghost'} hover:text-white"
															on:click={() => currentPage = pageNum}
														>
															{pageNum}
														</button>
													{/each}
												{:else}
													<!-- Always show first page -->
													{#if currentPage === 1}
														<button class="join-item btn btn-sm btn-active hover:text-white" disabled>1</button>
													{:else}
														<button
															class="join-item btn btn-sm btn-ghost hover:text-white"
															on:click={() => currentPage = 1}
														>
															1
														</button>
													{/if}
													{#if currentPage > 3}
														<button class="join-item btn btn-sm btn-disabled" disabled>...</button>
													{/if}
													{#if currentPage > 2 && currentPage < totalPages}
														{@const prevPage = currentPage - 1}
														<button
															class="join-item btn btn-sm btn-ghost hover:text-white"
															on:click={() => currentPage = prevPage}
														>
															{prevPage}
														</button>
													{/if}
													{#if currentPage > 1 && currentPage < totalPages}
														<button class="join-item btn btn-sm btn-active hover:text-white" disabled>
															{currentPage}
														</button>
													{/if}
													{#if currentPage < totalPages - 1 && currentPage > 1}
														{@const nextPage = currentPage + 1}
														<button
															class="join-item btn btn-sm btn-ghost hover:text-white"
															on:click={() => currentPage = nextPage}
														>
															{nextPage}
														</button>
													{/if}
													{#if currentPage < totalPages - 2}
														<button class="join-item btn btn-sm btn-disabled" disabled>...</button>
													{/if}
													<!-- Always show last page -->
													{#if currentPage === totalPages}
														<button class="join-item btn btn-sm btn-active hover:text-white" disabled>
															{totalPages}
														</button>
													{:else}
														<button
															class="join-item btn btn-sm btn-ghost hover:text-white"
															on:click={() => currentPage = totalPages}
														>
															{totalPages}
														</button>
													{/if}
												{/if}
											</div>
											<button
												class="btn btn-sm btn-ghost hover:text-white"
												on:click={() => currentPage = Math.min(totalPages, currentPage + 1)}
												disabled={currentPage === totalPages}
											>
												Selanjutnya
												<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
													<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
												</svg>
											</button>
										</div>
									{/if}
								</div>
							</div>
						{/if}
					</div>
				{:else}
					<div class="card bg-base-100 shadow-lg">
						<div class="card-body text-center py-16">
							<svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-4 text-base-content opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
							</svg>
							<p class="text-base-content opacity-70 text-lg mb-2">Pilih periode untuk melihat detail</p>
							<p class="text-base-content opacity-50 text-sm mb-6">Atau buat periode baru untuk memulai</p>
							<button class="btn btn-primary text-white btn-lg" on:click={createPeriod}>
								<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
								</svg>
								Buat Periode Baru
							</button>
						</div>
					</div>
				{/if}
			</div>
		</div>
	{/if}
</div>

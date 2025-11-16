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
	}

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
					<div class="card-body">
						<h2 class="card-title text-base-content">Daftar Periode</h2>
						<div class="space-y-2 mt-4 max-h-[600px] overflow-y-auto">
							{#each periods as period}
								<button
									class={`btn btn-block justify-start ${
										selectedPeriod?.id === period.id ? 'btn-primary text-white' : 'btn-ghost text-base-content'
									}`}
									on:click={() => {
										selectedPeriod = period;
										showPreview = false;
										previewData = null;
									}}
								>
									<div class="flex-1 text-left">
										<div class="font-semibold">
											{new Date(period.year, period.month - 1).toLocaleString('id-ID', {
												month: 'long',
												year: 'numeric'
											})}
										</div>
										<div class="text-xs opacity-70">
											<span class="badge {getStatusBadgeClass(period.status)} badge-sm">
												{getStatusLabel(period.status)}
											</span>
										</div>
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
					<div class="space-y-6">
						<!-- Period Info -->
						<div class="card bg-base-100 shadow-lg">
							<div class="card-body">
								<div class="flex justify-between items-start">
									<div>
										<h2 class="card-title text-base-content">
											{new Date(selectedPeriod.year, selectedPeriod.month - 1).toLocaleString('id-ID', {
												month: 'long',
												year: 'numeric'
											})}
										</h2>
										<p class="text-base-content opacity-70 mt-1">
											Status: <span class="badge {getStatusBadgeClass(selectedPeriod.status)} badge-sm">
												{getStatusLabel(selectedPeriod.status)}
											</span>
										</p>
									</div>
									<div class="flex gap-2">
										{#if selectedPeriod.status === 'draft'}
											<button
												class="btn btn-sm btn-info text-white"
												on:click={() => updatePeriodStatus('reviewed')}
											>
												Setujui Review
											</button>
										{/if}
										{#if selectedPeriod.status === 'reviewed'}
											<button
												class="btn btn-sm btn-success text-white"
												on:click={() => updatePeriodStatus('approved')}
											>
												Setujui
											</button>
										{/if}
										{#if selectedPeriod.status === 'approved'}
											<button
												class="btn btn-sm btn-primary text-white"
												on:click={() => updatePeriodStatus('posted')}
											>
												Posting
											</button>
										{/if}
									</div>
								</div>
							</div>
						</div>

						<!-- Summary -->
						{#if periodSummary}
							<div class="grid grid-cols-1 md:grid-cols-4 gap-4">
								<div class="stat bg-base-200 rounded-lg shadow">
									<div class="stat-title text-base-content opacity-70">Total Karyawan</div>
									<div class="stat-value text-primary text-2xl">{periodSummary.total_employees}</div>
								</div>
								<div class="stat bg-base-200 rounded-lg shadow">
									<div class="stat-title text-base-content opacity-70">Total Bruto</div>
									<div class="stat-value text-secondary text-xl">{formatCurrency(periodSummary.total_bruto)}</div>
								</div>
								<div class="stat bg-base-200 rounded-lg shadow">
									<div class="stat-title text-base-content opacity-70">Total Neto</div>
									<div class="stat-value text-accent text-xl">{formatCurrency(periodSummary.total_neto)}</div>
								</div>
								<div class="stat bg-base-200 rounded-lg shadow">
									<div class="stat-title text-base-content opacity-70">Total PPh 21</div>
									<div class="stat-value text-success text-xl">{formatCurrency(periodSummary.total_pph21)}</div>
								</div>
							</div>
						{/if}

						<!-- Actions -->
						<div class="card bg-base-100 shadow-lg">
							<div class="card-body">
								<h3 class="card-title text-base-content">Aksi</h3>
								<div class="flex flex-wrap gap-3 mt-4">
									{#if selectedPeriod.status !== 'posted'}
										<button
											class="btn btn-primary text-white"
											on:click={previewPayroll}
											disabled={previewLoading}
										>
											{#if previewLoading}
												<span class="loading loading-spinner loading-sm"></span>
												Loading...
											{:else}
												<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
													<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
													<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
												</svg>
												Preview Payroll
											{/if}
										</button>
									{/if}
									{#if selectedPeriod.status === 'approved'}
										<button
											class="btn btn-success text-white"
											on:click={commitPayroll}
											disabled={commitLoading}
										>
											{#if commitLoading}
												<span class="loading loading-spinner loading-sm"></span>
												Committing...
											{:else}
												<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
													<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
												</svg>
												Commit Payroll
											{/if}
										</button>
									{/if}
									<a
										href="/payroll/input?period={selectedPeriod.id}"
										class="btn btn-outline btn-neutral text-base-content"
									>
										<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
										</svg>
										Input Data
									</a>
								</div>
							</div>
						</div>

						<!-- Preview Modal -->
						{#if showPreview && previewData}
							<div class="card bg-base-100 shadow-lg">
								<div class="card-body">
									<div class="flex justify-between items-center mb-4">
										<h3 class="card-title text-base-content">Preview Payroll - {previewData.period}</h3>
										<button class="btn btn-sm btn-ghost" on:click={() => (showPreview = false)}>✕</button>
									</div>
									<div class="overflow-x-auto">
										<table class="table table-zebra">
											<thead>
												<tr>
													<th class="text-base-content">Nama</th>
													<th class="text-base-content text-right">Bruto</th>
													<th class="text-base-content text-right">Neto</th>
													<th class="text-base-content text-right">PPh 21</th>
												</tr>
											</thead>
											<tbody>
												{#each previewData.previews as preview}
													<tr>
														<td class="text-base-content">{preview.person_name}</td>
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
									<h3 class="card-title text-base-content">Detail Perhitungan</h3>
									<div class="overflow-x-auto mt-4">
										<table class="table table-zebra">
											<thead>
												<tr>
													<th class="text-base-content">Nama</th>
													<th class="text-base-content">Unit</th>
													<th class="text-base-content text-right">Bruto</th>
													<th class="text-base-content text-right">Neto</th>
													<th class="text-base-content text-right">PPh 21</th>
												</tr>
											</thead>
											<tbody>
												{#each periodSummary.calculations as calc}
													<tr>
														<td class="text-base-content">{calc.person_name}</td>
														<td class="text-base-content opacity-70">{calc.org_unit}</td>
														<td class="text-base-content text-right">{formatCurrency(calc.bruto)}</td>
														<td class="text-base-content text-right">{formatCurrency(calc.neto_masa)}</td>
														<td class="text-base-content text-right font-semibold text-primary">
															{formatCurrency(calc.pph21_masa)}
														</td>
													</tr>
												{/each}
											</tbody>
										</table>
									</div>
								</div>
							</div>
						{/if}
					</div>
				{:else}
					<div class="card bg-base-100 shadow-lg">
						<div class="card-body text-center py-12">
							<p class="text-base-content opacity-50 mb-4">Pilih periode untuk melihat detail</p>
							<button class="btn btn-primary text-white" on:click={createPeriod}>
								Buat Periode Baru
							</button>
						</div>
					</div>
				{/if}
			</div>
		</div>
	{/if}
</div>

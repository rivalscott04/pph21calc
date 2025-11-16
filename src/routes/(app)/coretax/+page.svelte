<script lang="ts">
	import { onMount } from 'svelte';
	import { coretaxApi, type CoreTaxLog } from '$lib/api/coretax.js';
	import { payrollApi, type Period } from '$lib/api/payroll.js';
	import { toast } from '$lib/stores/toast.js';

	let loading = true;
	let periods: Period[] = [];
	let selectedPeriod: Period | null = null;
	let logs: CoreTaxLog[] = [];
	let exportLoading = false;
	let uploadLoading = false;
	let showExportModal = false;
	let exportData: any = null;
	let showUploadConfirmModal = false;

	function formatCurrency(amount: number): string {
		return new Intl.NumberFormat('id-ID', {
			style: 'currency',
			currency: 'IDR',
			minimumFractionDigits: 0
		}).format(amount);
	}

	function getStatusBadgeClass(status: string): string {
		const statusMap: Record<string, string> = {
			pending: 'badge-neutral',
			sent: 'badge-info',
			validated: 'badge-success',
			failed: 'badge-error'
		};
		return statusMap[status] || 'badge-neutral';
	}

	function getStatusLabel(status: string): string {
		const statusMap: Record<string, string> = {
			pending: 'Pending',
			sent: 'Terkirim',
			validated: 'Tervalidasi',
			failed: 'Gagal'
		};
		return statusMap[status] || status;
	}

	async function loadPeriods() {
		try {
			const response = await payrollApi.listPeriods({ status: 'posted', per_page: 50 });
			periods = response.data || [];
			if (periods.length > 0 && !selectedPeriod) {
				selectedPeriod = periods[0];
			}
		} catch (error) {
			console.error('Failed to load periods:', error);
			toast.error('Gagal memuat daftar periode');
		}
	}

	async function loadLogs() {
		try {
			const response = await coretaxApi.listLogs({ per_page: 20 });
			logs = response.data || [];
		} catch (error) {
			console.error('Failed to load logs:', error);
			toast.error('Gagal memuat log CoreTax');
		} finally {
			loading = false;
		}
	}

	async function exportBPA() {
		if (!selectedPeriod) {
			toast.error('Pilih periode terlebih dahulu');
			return;
		}

		exportLoading = true;
		try {
			exportData = await coretaxApi.export(selectedPeriod.id);
			showExportModal = true;
			toast.success('BPA data berhasil di-generate');
		} catch (error: any) {
			console.error('Failed to export BPA:', error);
			toast.error(error.message || 'Gagal generate BPA data');
		} finally {
			exportLoading = false;
		}
	}

	function openUploadConfirmModal() {
		if (!selectedPeriod) {
			toast.error('Pilih periode terlebih dahulu');
			return;
		}
		showUploadConfirmModal = true;
	}

	function closeUploadConfirmModal() {
		showUploadConfirmModal = false;
	}

	async function confirmUpload() {
		if (!selectedPeriod) return;

		uploadLoading = true;
		closeUploadConfirmModal();
		try {
			const result = await coretaxApi.upload(selectedPeriod.id);
			if (result.status === 'sent') {
				toast.success('Data berhasil dikirim ke CoreTax');
			} else {
				toast.error('Gagal mengirim data ke CoreTax');
			}
			await loadLogs();
		} catch (error: any) {
			console.error('Failed to upload to CoreTax:', error);
			toast.error(error.message || 'Gagal mengirim data ke CoreTax');
		} finally {
			uploadLoading = false;
		}
	}

	function downloadJSON(data: any, filename: string) {
		const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
		const url = URL.createObjectURL(blob);
		const a = document.createElement('a');
		a.href = url;
		a.download = filename;
		document.body.appendChild(a);
		a.click();
		document.body.removeChild(a);
		URL.revokeObjectURL(url);
	}

	onMount(async () => {
		await Promise.all([loadPeriods(), loadLogs()]);
	});
</script>

<div class="space-y-6">
	<div class="flex justify-between items-center">
		<div>
			<h1 class="text-3xl font-bold text-base-content">CoreTax Export</h1>
			<p class="text-base-content opacity-70 mt-1">Ekspor dan kirim data BPA1/BPA2 ke CoreTax</p>
		</div>
	</div>

	<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
		<!-- Period Selection & Actions -->
		<div class="lg:col-span-1 space-y-6">
			<!-- Period Selection -->
			<div class="card bg-base-100 shadow-lg">
				<div class="card-body">
					<h2 class="card-title text-base-content">Pilih Periode</h2>
					<div class="form-control mt-4">
						<select
							class="select select-bordered w-full text-base-content"
							bind:value={selectedPeriod}
							disabled={periods.length === 0}
						>
							<option value={null} disabled>Pilih periode...</option>
							{#each periods as period}
								<option value={period}>
									{new Date(period.year, period.month - 1).toLocaleString('id-ID', {
										month: 'long',
										year: 'numeric'
									})}
								</option>
							{/each}
						</select>
						<div class="label">
							<span class="label-text-alt text-base-content opacity-60">
								Hanya periode yang sudah diposting
							</span>
						</div>
					</div>
				</div>
			</div>

			<!-- Actions -->
			<div class="card bg-base-100 shadow-lg">
				<div class="card-body">
					<h2 class="card-title text-base-content">Aksi</h2>
					<div class="space-y-3 mt-4">
						<button
							class="btn btn-brand text-white w-full"
							on:click={exportBPA}
							disabled={!selectedPeriod || exportLoading}
						>
							{#if exportLoading}
								<span class="loading loading-spinner loading-sm"></span>
								Generating...
							{:else}
								<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
								</svg>
								Generate BPA
							{/if}
						</button>
						<button
							class="btn btn-success text-white w-full"
							on:click={openUploadConfirmModal}
							disabled={!selectedPeriod || uploadLoading}
						>
							{#if uploadLoading}
								<span class="loading loading-spinner loading-sm"></span>
								Uploading...
							{:else}
								<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
								</svg>
								Upload ke CoreTax
							{/if}
						</button>
					</div>
					{#if periods.length === 0}
						<div class="alert alert-warning mt-4">
							<svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-7 4h13a2 2 0 002-2V7a2 2 0 00-2-2H6a2 2 0 00-2 2v10a2 2 0 002 2z" />
							</svg>
							<span class="text-sm text-base-content">Tidak ada periode yang sudah diposting</span>
						</div>
					{/if}
				</div>
			</div>
		</div>

		<!-- Logs -->
		<div class="lg:col-span-2">
			<div class="card bg-base-100 shadow-lg">
				<div class="card-body">
					<h2 class="card-title text-base-content">Log CoreTax</h2>
					{#if loading}
						<div class="flex justify-center items-center py-12">
							<span class="loading loading-spinner loading-lg text-primary"></span>
						</div>
					{:else if logs.length > 0}
						<div class="overflow-x-auto mt-4">
							<table class="table table-zebra">
								<thead>
									<tr>
										<th class="text-base-content">Periode</th>
										<th class="text-base-content">Status</th>
										<th class="text-base-content">Ref No</th>
										<th class="text-base-content">Waktu</th>
										<th class="text-base-content">Aksi</th>
									</tr>
								</thead>
								<tbody>
									{#each logs as log}
										<tr>
											<td class="text-base-content">
												{log.period
													? new Date(log.period.year, log.period.month - 1).toLocaleString('id-ID', {
															month: 'long',
															year: 'numeric'
														})
													: '-'}
											</td>
											<td>
												<span class="badge {getStatusBadgeClass(log.status)} badge-sm">
													{getStatusLabel(log.status)}
												</span>
											</td>
											<td class="text-base-content opacity-70 font-mono text-sm">
												{log.ref_no || '-'}
											</td>
											<td class="text-base-content opacity-70 text-sm">
												{new Date(log.created_at).toLocaleString('id-ID')}
											</td>
											<td>
												<button
													class="btn btn-sm btn-ghost"
													on:click={() => {
														exportData = log.payload_json;
														showExportModal = true;
													}}
												>
													Lihat
												</button>
											</td>
										</tr>
									{/each}
								</tbody>
							</table>
						</div>
					{:else}
						<div class="text-center py-12">
							<p class="text-base-content opacity-50">Belum ada log CoreTax</p>
						</div>
					{/if}
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Export Modal -->
{#if showExportModal && exportData}
	<div class="modal modal-open">
		<div class="modal-box max-w-4xl">
			<h3 class="text-2xl font-bold text-base-content mb-4">BPA Data</h3>
			<div class="flex justify-end gap-2 mb-4">
				<button
					class="btn btn-sm btn-brand text-white"
					on:click={() => downloadJSON(exportData, `bpa-${selectedPeriod?.year}-${selectedPeriod?.month}.json`)}
				>
					<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
					</svg>
					Download JSON
				</button>
				<button class="btn btn-sm btn-ghost" on:click={() => (showExportModal = false)}>Tutup</button>
			</div>
			<div class="overflow-x-auto max-h-[600px]">
				<pre class="bg-base-200 p-4 rounded-lg text-sm text-base-content font-mono overflow-auto">{JSON.stringify(exportData, null, 2)}</pre>
			</div>
		</div>
		<form method="dialog" class="modal-backdrop">
			<button on:click={() => (showExportModal = false)}>close</button>
		</form>
	</div>
{/if}

<!-- Upload Confirmation Modal -->
{#if showUploadConfirmModal}
	<div class="modal modal-open">
		<div class="modal-box">
			<div class="flex items-center gap-4 mb-6">
				<div class="flex-shrink-0">
					<div class="w-12 h-12 rounded-full bg-warning/20 flex items-center justify-center">
						<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-warning" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
						</svg>
					</div>
				</div>
				<div class="flex-1">
					<h3 class="text-2xl font-bold text-base-content mb-1">Kirim ke CoreTax</h3>
					<p class="text-sm text-base-content opacity-70">Konfirmasi pengiriman data</p>
				</div>
			</div>

			<div class="bg-base-200 rounded-lg p-4 mb-6">
				<p class="text-base-content">
					Apakah Anda yakin ingin mengirim data ke CoreTax?
				</p>
				{#if selectedPeriod}
					<p class="text-sm text-base-content opacity-70 mt-2">
						Periode: <span class="font-semibold">
							{new Date(selectedPeriod.year, selectedPeriod.month - 1).toLocaleString('id-ID', {
								month: 'long',
								year: 'numeric'
							})}
						</span>
					</p>
				{/if}
			</div>

			<div class="modal-action">
				<button class="btn btn-outline btn-neutral text-base-content" on:click={closeUploadConfirmModal}>
					Batal
				</button>
				<button class="btn btn-success text-white" on:click={confirmUpload} disabled={uploadLoading}>
					{#if uploadLoading}
						<span class="loading loading-spinner loading-sm"></span>
						Mengirim...
					{:else}
						<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
						</svg>
						Kirim
					{/if}
				</button>
			</div>
		</div>
		<form method="dialog" class="modal-backdrop" on:submit|preventDefault={closeUploadConfirmModal}>
			<button>close</button>
		</form>
	</div>
{/if}

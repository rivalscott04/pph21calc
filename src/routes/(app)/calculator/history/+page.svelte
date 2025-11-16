<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { calculatorApi, type EmployeeHistoryListItem } from '$lib/api/calculator.js';
	import { toast } from '$lib/stores/toast.js';

	let loadingHistory = false;
	let employees: EmployeeHistoryListItem[] = [];
	let selectedYear: number | null = null;
	let currentYear = new Date().getFullYear();

	function formatCurrency(amount: number): string {
		return new Intl.NumberFormat('id-ID', {
			style: 'currency',
			currency: 'IDR',
			minimumFractionDigits: 0
		}).format(amount);
	}

	function formatDate(dateString: string): string {
		return new Date(dateString).toLocaleDateString('id-ID', {
			day: '2-digit',
			month: '2-digit',
			year: 'numeric'
		});
	}

	async function loadHistory() {
		loadingHistory = true;
		try {
			const response = await calculatorApi.getEmployeeHistoryList({
				year: selectedYear || undefined
			});
			employees = response.data || [];
		} catch (error: any) {
			console.error('Load history error:', error);
			toast.error('Gagal memuat daftar pegawai');
			employees = [];
		} finally {
			loadingHistory = false;
		}
	}

	function handleDetailClick(employmentId: number, year: number) {
		goto(`/calculator/history/${employmentId}?year=${year}`);
	}

	onMount(() => {
		loadHistory();
	});
</script>

<div class="space-y-6">
	<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
		<div>
			<h1 class="text-3xl font-bold text-base-content">Daftar Pegawai PPh21</h1>
			<p class="text-base-content opacity-70 mt-1">Lihat riwayat perhitungan PPh21 per pegawai</p>
		</div>
		<div class="flex gap-2">
			<select
				class="select select-bordered select-sm text-base-content"
				bind:value={selectedYear}
				on:change={loadHistory}
			>
				<option value={null}>Semua Tahun</option>
				{#each Array.from({ length: 10 }, (_, i) => currentYear - i) as y}
					<option value={y}>{y}</option>
				{/each}
			</select>
		</div>
	</div>

	<div class="card bg-base-100 shadow-lg">
		<div class="card-body">
			{#if loadingHistory}
				<div class="flex justify-center items-center py-12">
					<span class="loading loading-spinner loading-lg text-primary"></span>
				</div>
			{:else if employees.length > 0}
				<div class="overflow-x-auto">
					<table class="table table-zebra">
						<thead>
							<tr>
								<th class="text-base-content">Tanggal Terakhir</th>
								<th class="text-base-content">Nama Pegawai</th>
								<th class="text-base-content">PTKP</th>
								<th class="text-base-content">NPWP</th>
								<th class="text-base-content">Tahun</th>
								<th class="text-base-content">Status</th>
								<th class="text-base-content text-right">Aksi</th>
							</tr>
						</thead>
						<tbody>
							{#each employees as employee}
								<tr>
									<td class="text-base-content opacity-70">
										{formatDate(employee.latest_calculation_date)}
									</td>
									<td class="text-base-content">
										<div class="font-semibold">{employee.person_name}</div>
									</td>
									<td>
										<span class="badge badge-neutral">{employee.ptkp_code}</span>
									</td>
									<td>
										{#if employee.has_npwp}
											<span class="badge badge-info">Ya</span>
										{:else}
											<span class="badge badge-warning">Tidak</span>
										{/if}
									</td>
									<td class="text-base-content">{employee.year}</td>
									<td>
										<span class="badge badge-soft badge-success">
											{employee.status_text}
										</span>
									</td>
									<td class="text-right">
										<button
											class="btn btn-sm btn-primary"
											on:click={() => handleDetailClick(employee.employment_id, employee.year)}
										>
											Detail
										</button>
									</td>
								</tr>
							{/each}
						</tbody>
					</table>
				</div>
			{:else}
				<div class="text-center py-12">
					<svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-base-content opacity-30 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
					</svg>
					<p class="text-base-content opacity-50">Belum ada riwayat perhitungan</p>
					<p class="text-sm text-base-content opacity-40 mt-2">Simpan perhitungan untuk melihat riwayat di sini</p>
				</div>
			{/if}
		</div>
	</div>
</div>

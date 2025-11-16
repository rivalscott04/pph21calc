<script lang="ts">
	import { onMount } from 'svelte';
	import { page } from '$app/stores';
	import { goto } from '$app/navigation';
	import FullCalendar from 'svelte-fullcalendar';
	import dayGridPlugin from '@fullcalendar/daygrid';
	import type { EventInput } from '@fullcalendar/core';
	import { calculatorApi, type EmployeeHistoryDetail, type Period } from '$lib/api/calculator.js';
	import { toast } from '$lib/stores/toast.js';

	let loading = false;
	let detail: EmployeeHistoryDetail | null = null;
	let selectedPeriod: Period | null = null;
	let modalOpen = false;
	let monthDetailModal: HTMLDialogElement | null = null;
	let calendarOptions: any = null;
	let calendarEvents: EventInput[] = [];
	let viewMode: 'month' | 'day' = 'month'; // 'month' = list bulanan, 'day' = kalender harian

	$: employmentId = parseInt($page.params.employmentId || '0');
	$: year = parseInt($page.url.searchParams.get('year') || new Date().getFullYear().toString());

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
			month: 'long',
			year: 'numeric'
		});
	}

	function getInitials(name: string): string {
		return name
			.split(' ')
			.map((n) => n[0])
			.join('')
			.toUpperCase()
			.substring(0, 2);
	}

	function monthNameShort(m: number): string {
		const names = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
		return names[m - 1] || '';
	}

	function monthNameFull(m: number): string {
		const names = [
			'Januari',
			'Februari',
			'Maret',
			'April',
			'Mei',
			'Juni',
			'Juli',
			'Agustus',
			'September',
			'Oktober',
			'November',
			'Desember'
		];
		return names[m - 1] || '';
	}

	function buildEventsFromPeriods(periods: Period[]): EventInput[] {
		return periods.map((p) => {
			const amount = p.pph21_masa ?? 0;
			const formatted = formatCurrency(amount).replace('Rp', '').trim();
			return {
				id: `pph-${p.year}-${p.month}`,
				title: `PPh21 ${monthNameShort(p.month)} ${p.year} – ${formatted}`,
				date: p.calculation_date ?? `${p.year}-${String(p.month).padStart(2, '0')}-28`,
				// Use better colors with proper contrast
				backgroundColor: p.is_reconciliation ? '#f59e0b' : '#10b981', // amber-500 for Dec, emerald-500 for others
				borderColor: p.is_reconciliation ? '#d97706' : '#059669', // darker borders
				textColor: '#ffffff', // white text for readability
				extendedProps: {
					period: p
				}
			};
		});
	}

	function handleEventClick(info: any) {
		const period = info.event.extendedProps.period as Period;
		if (period) {
			selectedPeriod = period;
			modalOpen = true;
			if (monthDetailModal) {
				monthDetailModal.showModal();
			}
		}
	}

	function handlePeriodClick(period: Period) {
		selectedPeriod = period;
		modalOpen = true;
		if (monthDetailModal) {
			monthDetailModal.showModal();
		}
	}

	function closeModal() {
		modalOpen = false;
		selectedPeriod = null;
		if (monthDetailModal) {
			monthDetailModal.close();
		}
	}

	async function loadDetail() {
		if (!employmentId) return;

		loading = true;
		try {
			detail = await calculatorApi.getEmployeeHistoryDetail(employmentId, { year });
			if (detail && detail.periods) {
				calendarEvents = buildEventsFromPeriods(detail.periods);
			}
		} catch (error: any) {
			console.error('Load detail error:', error);
			toast.error('Gagal memuat detail pegawai');
			detail = null;
			calendarEvents = [];
		} finally {
			loading = false;
		}
	}

	$: if (detail && detail.periods) {
		calendarEvents = buildEventsFromPeriods(detail.periods);
		if (viewMode === 'day') {
			calendarOptions = {
				plugins: [dayGridPlugin],
				initialView: 'dayGridMonth',
				initialDate: `${year}-01-01`,
				headerToolbar: {
					left: 'prev,next today',
					center: 'title',
					right: ''
				},
				events: calendarEvents,
				eventClick: handleEventClick,
				firstDay: 1, // Monday
				height: 'auto',
				contentHeight: 'auto'
			};
		} else {
			// Reset calendarOptions for month view (we use custom list instead)
			calendarOptions = null;
		}
	}

	onMount(() => {
		loadDetail();
	});

	$: if (year) {
		loadDetail();
	}
</script>

<div class="space-y-6">
	<!-- Back button -->
	<button class="btn btn-ghost btn-sm" on:click={() => goto('/calculator/history')}>
		← Kembali ke Daftar
	</button>

	{#if loading}
		<div class="flex justify-center items-center py-12">
			<span class="loading loading-spinner loading-lg text-primary"></span>
		</div>
	{:else if detail}
		<!-- Employee Profile Card -->
		<div class="card bg-base-100 shadow-xl rounded-xl border border-base-300">
			<div class="card-body">
				<div class="flex flex-col sm:flex-row gap-6 items-start sm:items-center">
					<!-- Avatar/Initials -->
					<div class="avatar placeholder">
						<div class="bg-primary text-primary-content rounded-full w-20 h-20 flex items-center justify-center">
							<span class="text-2xl font-bold">{getInitials(detail.employee.name)}</span>
						</div>
					</div>

					<!-- Employee Info -->
					<div class="flex-1">
						<h2 class="text-2xl font-bold text-base-content mb-2">{detail.employee.name}</h2>
						<div class="flex flex-wrap gap-4 text-sm text-base-content opacity-70">
							{#if detail.employee.nip}
								<div>
									<span class="font-semibold">NIP:</span> {detail.employee.nip}
								</div>
							{/if}
							{#if detail.employee.position}
								<div>
									<span class="font-semibold">Jabatan:</span> {detail.employee.position}
								</div>
							{/if}
							<div>
								<span class="font-semibold">Tahun Pajak:</span> {detail.year}
							</div>
						</div>
						<div class="flex flex-wrap gap-2 mt-3">
							<span class="badge badge-neutral badge-lg">{detail.employee.ptkp_code}</span>
							{#if detail.employee.has_npwp}
								<span class="badge badge-info badge-lg">NPWP: Ya</span>
								{#if detail.employee.npwp_number}
									<span class="badge badge-info badge-lg">{detail.employee.npwp_number}</span>
								{/if}
							{:else}
								<span class="badge badge-warning badge-lg">NPWP: Tidak</span>
							{/if}
						</div>
					</div>

					<!-- Summary Stats -->
					<div class="flex flex-col gap-2 text-right">
						<div>
							<div class="text-xs text-base-content opacity-70">Bulan Dihitung</div>
							<div class="text-lg font-semibold text-base-content">
								{detail.summary.months_with_calculation.length} bulan
							</div>
						</div>
						<div>
							<div class="text-xs text-base-content opacity-70">Total PPh21 YTD</div>
							<div class="text-lg font-bold text-primary">
								{formatCurrency(detail.summary.total_pph21_ytd)}
							</div>
						</div>
						{#if detail.summary.total_pph21_year > detail.summary.total_pph21_ytd}
							<div>
								<div class="text-xs text-base-content opacity-70">Total PPh21 Tahun</div>
								<div class="text-lg font-bold text-warning">
									{formatCurrency(detail.summary.total_pph21_year)}
								</div>
							</div>
						{/if}
						<div>
							<div class="text-xs text-base-content opacity-70">Total PKP Tahun</div>
							<div class="text-lg font-semibold text-base-content">
								{formatCurrency(detail.summary.total_pkp_year)}
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Calendar View -->
		<div class="bg-base-100 rounded-xl border border-base-300 shadow-lg overflow-hidden">
			<div class="p-6 border-b border-base-300">
				<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
					<h3 class="text-2xl font-bold text-base-content">Riwayat PPh21 {detail.year}</h3>
					<div class="flex flex-wrap gap-3 items-center">
						<!-- View Toggle -->
						<select
							class="select select-bordered select-sm text-base-content bg-base-100"
							bind:value={viewMode}
						>
							<option value="month">Tampilan Bulanan</option>
							<option value="day">Tampilan Kalender</option>
						</select>
						<!-- Legend -->
						<div class="flex flex-wrap gap-3 items-center text-xs sm:text-sm">
							<div class="flex items-center gap-2">
								<div class="w-3 h-3 rounded bg-success border border-success/50"></div>
								<span class="text-base-content">Sudah dihitung</span>
							</div>
							<div class="flex items-center gap-2">
								<div class="w-3 h-3 rounded bg-warning border border-warning/50"></div>
								<span class="text-base-content">Rekonsiliasi</span>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="p-6">
				{#if viewMode === 'month'}
					<!-- Monthly List View - Simpler UX -->
					{#if detail.periods && detail.periods.length > 0}
						<div class="space-y-3">
							{#each detail.periods as period}
								<button
									class="w-full text-left card bg-base-100 border border-base-300 hover:border-primary hover:shadow-md transition-all cursor-pointer"
									on:click={() => handlePeriodClick(period)}
								>
									<div class="card-body p-4">
										<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
											<div class="flex-1">
												<div class="flex items-center gap-3 mb-2">
													<h4 class="text-lg font-bold text-base-content">
														{monthNameFull(period.month)} {period.year}
													</h4>
													{#if period.is_reconciliation}
														<span class="badge badge-warning">Rekonsiliasi</span>
													{:else}
														<span class="badge badge-success">TER</span>
													{/if}
												</div>
												<div class="text-sm text-base-content opacity-70">
													Tanggal: {formatDate(period.calculation_date)}
												</div>
											</div>
											<div class="text-right">
												<div class="text-sm text-base-content opacity-70 mb-1">PPh21 Masa</div>
												<div class="text-xl font-bold text-primary">
													{formatCurrency(period.pph21_masa)}
												</div>
												<div class="text-xs text-base-content opacity-60 mt-1">
													YTD: {formatCurrency(period.pph21_ytd)}
												</div>
											</div>
										</div>
									</div>
								</button>
							{/each}
						</div>
					{:else}
						<div class="text-center py-12">
							<p class="text-base-content opacity-50">Belum ada perhitungan untuk tahun {detail.year}</p>
						</div>
					{/if}
				{:else if calendarOptions}
					<!-- Daily Calendar View -->
					<FullCalendar options={calendarOptions} />
				{:else}
					<div class="text-center py-12">
						<p class="text-base-content opacity-50">Memuat kalender...</p>
					</div>
				{/if}
			</div>
		</div>
	{:else}
		<div class="text-center py-12">
			<p class="text-base-content opacity-50">Data tidak ditemukan</p>
		</div>
	{/if}
</div>

<!-- Period Detail Modal -->
{#if selectedPeriod}
	<dialog bind:this={monthDetailModal} id="period-detail-modal" class="modal">
		<div class="modal-box max-w-2xl bg-base-100 text-base-content">
			<h3 class="font-bold text-lg mb-4 text-base-content">
				Detail Perhitungan PPh21 – {monthNameShort(selectedPeriod.month)} {selectedPeriod.year}
				{#if selectedPeriod.is_reconciliation}
					<span class="badge badge-warning ml-2">Rekonsiliasi</span>
				{/if}
			</h3>

			<div class="space-y-4">
				<!-- Calculation Details -->
				{#if selectedPeriod.bruto > 50000000}
					{@const isLikelyAnnual = true}
					{@const brutoMonthly = selectedPeriod.bruto / 12}
					{@const biayaJabatanMonthly = selectedPeriod.biaya_jabatan / 12}
					{@const iuranPensiunMonthly = selectedPeriod.iuran_pensiun / 12}
					{@const zakatMonthly = selectedPeriod.zakat / 12}
					{@const netoMonthly = selectedPeriod.neto_masa / 12}
					<div class="grid grid-cols-2 gap-4">
						<div>
							<div class="text-sm text-base-content opacity-70">Bruto (Bulanan)</div>
							<div class="text-lg font-semibold text-base-content">{formatCurrency(brutoMonthly)}</div>
							<div class="text-xs text-base-content opacity-60 mt-1">
								Tahunan: {formatCurrency(selectedPeriod.bruto)}
							</div>
						</div>
						<div>
							<div class="text-sm text-base-content opacity-70">Biaya Jabatan (Bulanan)</div>
							<div class="text-lg font-semibold text-base-content">{formatCurrency(biayaJabatanMonthly)}</div>
							<div class="text-xs text-base-content opacity-60 mt-1">
								Tahunan: {formatCurrency(selectedPeriod.biaya_jabatan)}
							</div>
						</div>
						<div>
							<div class="text-sm text-base-content opacity-70">Iuran Pensiun (Bulanan)</div>
							<div class="text-lg font-semibold text-base-content">{formatCurrency(iuranPensiunMonthly)}</div>
							<div class="text-xs text-base-content opacity-60 mt-1">
								Tahunan: {formatCurrency(selectedPeriod.iuran_pensiun)}
							</div>
						</div>
						<div>
							<div class="text-sm text-base-content opacity-70">Zakat (Bulanan)</div>
							<div class="text-lg font-semibold text-base-content">{formatCurrency(zakatMonthly)}</div>
							<div class="text-xs text-base-content opacity-60 mt-1">
								Tahunan: {formatCurrency(selectedPeriod.zakat)}
							</div>
						</div>
					</div>
				{:else}
					<div class="grid grid-cols-2 gap-4">
						<div>
							<div class="text-sm text-base-content opacity-70">Bruto</div>
							<div class="text-lg font-semibold text-base-content">{formatCurrency(selectedPeriod.bruto)}</div>
						</div>
						<div>
							<div class="text-sm text-base-content opacity-70">Biaya Jabatan</div>
							<div class="text-lg font-semibold text-base-content">{formatCurrency(selectedPeriod.biaya_jabatan)}</div>
						</div>
						<div>
							<div class="text-sm text-base-content opacity-70">Iuran Pensiun</div>
							<div class="text-lg font-semibold text-base-content">{formatCurrency(selectedPeriod.iuran_pensiun)}</div>
						</div>
						<div>
							<div class="text-sm text-base-content opacity-70">Zakat</div>
							<div class="text-lg font-semibold text-base-content">{formatCurrency(selectedPeriod.zakat)}</div>
						</div>
					</div>
				{/if}

				<div class="divider"></div>

				<div class="grid grid-cols-2 gap-4">
					{#if selectedPeriod.bruto > 50000000}
						{@const netoMonthly = selectedPeriod.neto_masa / 12}
						<div>
							<div class="text-sm text-base-content opacity-70">Neto Masa (Bulanan)</div>
							<div class="text-lg font-semibold text-base-content">{formatCurrency(netoMonthly)}</div>
							<div class="text-xs text-base-content opacity-60 mt-1">
								Tahunan: {formatCurrency(selectedPeriod.neto_masa)}
							</div>
						</div>
					{:else}
						<div>
							<div class="text-sm text-base-content opacity-70">Neto Masa</div>
							<div class="text-lg font-semibold text-base-content">{formatCurrency(selectedPeriod.neto_masa)}</div>
						</div>
					{/if}
					<div>
						<div class="text-sm text-base-content opacity-70">Neto (Annualized)</div>
						<div class="text-lg font-semibold text-base-content">{formatCurrency(selectedPeriod.neto_annualized)}</div>
					</div>
					<div>
						<div class="text-sm text-base-content opacity-70">PTKP Tahunan</div>
						<div class="text-lg font-semibold text-base-content">{formatCurrency(selectedPeriod.ptkp_yearly)}</div>
					</div>
					<div>
						<div class="text-sm text-base-content opacity-70">PKP (Annualized)</div>
						<div class="text-lg font-semibold text-base-content">{formatCurrency(selectedPeriod.pkp_annualized)}</div>
					</div>
					<div>
						<div class="text-sm text-base-content opacity-70">PPh21 Masa</div>
						<div class="text-lg font-bold text-primary">
							{formatCurrency(selectedPeriod.pph21_masa)}
						</div>
					</div>
					<div>
						<div class="text-sm text-base-content opacity-70">PPh21 YTD</div>
						<div class="text-lg font-bold text-primary">
							{formatCurrency(selectedPeriod.pph21_ytd)}
						</div>
					</div>
				</div>

				<div class="divider"></div>

				<div>
					<div class="text-sm text-base-content opacity-70">Tanggal Perhitungan</div>
					<div class="text-base text-base-content">{formatDate(selectedPeriod.calculation_date)}</div>
				</div>

				{#if selectedPeriod.notes && selectedPeriod.notes.length > 0}
					<div class="divider"></div>
					<div>
						<div class="text-sm text-base-content opacity-70 mb-2">Catatan</div>
						<ul class="list-disc list-inside space-y-1">
							{#each selectedPeriod.notes as note}
								<li class="text-sm text-base-content opacity-80">{note}</li>
							{/each}
						</ul>
					</div>
				{/if}
			</div>

			<div class="modal-action">
				<form method="dialog">
					<button class="btn" on:click={closeModal}>Tutup</button>
				</form>
			</div>
		</div>
		<form method="dialog" class="modal-backdrop" on:submit|preventDefault={closeModal}>
			<button>close</button>
		</form>
	</dialog>
{/if}

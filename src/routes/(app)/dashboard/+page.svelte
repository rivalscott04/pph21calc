<script lang="ts">
	import { onMount } from 'svelte';
	import { dashboardApi, type DashboardSummary, type ChartData } from '$lib/api/dashboard.js';
	import { activityApi } from '$lib/api/activity.js';
	import { auth } from '$lib/stores/auth.js';
	import { get } from 'svelte/store';
	import { toast } from '$lib/stores/toast.js';
	import { hasAnyRole } from '$lib/utils/permissions.js';

	let summary: DashboardSummary | null = null;
	let pph21Chart: ChartData | null = null;
	let employeesChart: ChartData | null = null;
	let activityChart: ChartData | null = null;
	let recentActivities: any[] = [];
	let loading = true;
	let chartsLoading = true;

	// Format currency
	function formatCurrency(amount: number): string {
		return new Intl.NumberFormat('id-ID', {
			style: 'currency',
			currency: 'IDR',
			minimumFractionDigits: 0
		}).format(amount);
	}

	// Format number
	function formatNumber(num: number): string {
		return new Intl.NumberFormat('id-ID').format(num);
	}

	// Get month name
	function getMonthName(month: number): string {
		const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
		return months[month - 1] || '';
	}

	// Get action label and badge class
	function getActionInfo(action: string): { label: string; badgeClass: string } {
		const actionMap: Record<string, { label: string; badgeClass: string }> = {
			'insert': { label: 'created', badgeClass: 'badge-success' },
			'update': { label: 'updated', badgeClass: 'badge-info' },
			'delete': { label: 'deleted', badgeClass: 'badge-warning' }
		};
		return actionMap[action] || { label: action, badgeClass: 'badge-neutral' };
	}

	onMount(async () => {
		try {
			const user = get(auth);
			const tenantId = user?.tenant?.id;

			// Load summary first (most important data)
			summary = await dashboardApi.getSummary(tenantId);
			loading = false;

			// Load charts and activities in parallel (less critical, can load after)
			const [pph21Res, employeesRes, activityRes, activitiesRes] = await Promise.all([
				dashboardApi.getChart('pph21_monthly', tenantId),
				dashboardApi.getChart('employees_by_org', tenantId),
				dashboardApi.getChart('activity_timeline', tenantId),
				activityApi.list({ per_page: 10, page: 1 })
			]);

			pph21Chart = pph21Res;
			employeesChart = employeesRes;
			activityChart = activityRes;
			recentActivities = activitiesRes?.data || [];
			chartsLoading = false;
		} catch (error) {
			console.error('Failed to load dashboard data:', error);
			toast.error('Gagal memuat data dashboard');
			loading = false;
			chartsLoading = false;
		}
	});
</script>

<div class="space-y-6">
	<div class="flex justify-between items-center">
		<div>
			<h1 class="text-3xl font-bold text-base-content">Dashboard</h1>
			<p class="text-base-content opacity-70 mt-1">Ringkasan data dan aktivitas terbaru</p>
		</div>
	</div>

	{#if loading}
		<div class="flex justify-center items-center min-h-[400px]">
			<span class="loading loading-spinner loading-lg text-primary"></span>
		</div>
	{:else if summary}
		<!-- Summary Cards -->
		<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
			<!-- Karyawan Aktif -->
			<div class="stat bg-base-200 rounded-lg shadow">
				<div class="stat-figure text-primary">
					<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
					</svg>
				</div>
				<div class="stat-title text-base-content opacity-70">Karyawan Aktif</div>
				<div class="stat-value text-primary">{formatNumber(summary.total_employees)}</div>
				<div class="stat-desc text-base-content opacity-60">Sedang bekerja</div>
			</div>

			<!-- Total Pegawai Terdaftar -->
			<div class="stat bg-base-200 rounded-lg shadow">
				<div class="stat-figure text-secondary">
					<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
					</svg>
				</div>
				<div class="stat-title text-base-content opacity-70">Pegawai Terdaftar</div>
				<div class="stat-value text-secondary">{formatNumber(summary.total_persons)}</div>
				<div class="stat-desc text-base-content opacity-60">Total pegawai (aktif & nonaktif)</div>
			</div>

			<!-- PPh21 YTD -->
			<div class="stat bg-base-200 rounded-lg shadow">
				<div class="stat-figure text-accent">
					<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
					</svg>
				</div>
				<div class="stat-title text-base-content opacity-70">PPh21 YTD</div>
				<div class="stat-value text-accent text-2xl">{formatCurrency(summary.total_pph21_ytd)}</div>
				<div class="stat-desc text-base-content opacity-60">Tahun ini</div>
			</div>

			<!-- PPh21 This Month -->
			<div class="stat bg-base-200 rounded-lg shadow">
				<div class="stat-figure text-success">
					<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
					</svg>
				</div>
				<div class="stat-title text-base-content opacity-70">PPh21 Bulan Ini</div>
				<div class="stat-value text-success text-2xl">{formatCurrency(summary.total_pph21_this_month)}</div>
				<div class="stat-desc text-base-content opacity-60">Bulan berjalan</div>
			</div>
		</div>

		<!-- Additional Stats -->
		<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
			<div class="stat bg-base-200 rounded-lg shadow">
				<div class="stat-title text-base-content opacity-70">Total Periode</div>
				<div class="stat-value text-base-content">{formatNumber(summary.total_periods)}</div>
				<div class="stat-desc text-base-content opacity-60">Semua periode</div>
			</div>
			<div class="stat bg-base-200 rounded-lg shadow">
				<div class="stat-title text-base-content opacity-70">Periode Tahun Ini</div>
				<div class="stat-value text-base-content">{formatNumber(summary.current_year_periods)}</div>
				<div class="stat-desc text-base-content opacity-60">Periode aktif</div>
			</div>
			<div class="stat bg-base-200 rounded-lg shadow">
				<div class="stat-title text-base-content opacity-70">Periode Pending</div>
				<div class="stat-value text-warning">{formatNumber(summary.pending_periods)}</div>
				<div class="stat-desc text-base-content opacity-60">Menunggu persetujuan</div>
			</div>
		</div>

		<!-- Charts Row -->
		<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
			{#if chartsLoading}
				<div class="col-span-2 flex justify-center items-center py-12">
					<span class="loading loading-spinner loading-lg text-primary"></span>
				</div>
			{:else}
				<!-- PPh21 Monthly Chart -->
				{#if pph21Chart}
					<div class="card bg-base-100 shadow-lg">
						<div class="card-body">
							<h2 class="card-title text-base-content">PPh21 Bulanan</h2>
							<div class="space-y-2 mt-4">
								{#each pph21Chart.data as item}
									<div class="flex items-center gap-3">
										<div class="w-12 text-sm text-base-content opacity-70 font-medium">
											{getMonthName(item.month)}
										</div>
										<div class="flex-1">
											<div class="flex items-center gap-2">
												<progress 
													class="progress progress-primary flex-1" 
													value={item.total_pph21} 
													max={Math.max(...pph21Chart.data.map(d => d.total_pph21), 1)}
												></progress>
												<span class="text-sm font-semibold text-base-content min-w-[80px] text-right">
													{formatCurrency(item.total_pph21)}
												</span>
											</div>
										</div>
									</div>
								{/each}
							</div>
						</div>
					</div>
				{/if}

				<!-- Employees by Org Chart -->
				{#if employeesChart && employeesChart.data.length > 0}
					<div class="card bg-base-100 shadow-lg">
						<div class="card-body">
							<h2 class="card-title text-base-content">Karyawan per Unit</h2>
							<div class="space-y-3 mt-4">
								{#each employeesChart.data.slice(0, 10) as item}
									<div class="flex items-center gap-3">
										<div class="flex-1">
											<div class="flex justify-between items-center mb-1">
												<span class="text-sm font-medium text-base-content">{item.org_unit_name || 'N/A'}</span>
												<span class="text-sm font-semibold text-primary">{item.employee_count}</span>
											</div>
											<progress 
												class="progress progress-secondary" 
												value={item.employee_count} 
												max={Math.max(...employeesChart.data.map(d => d.employee_count), 1)}
											></progress>
										</div>
									</div>
								{/each}
							</div>
						</div>
					</div>
				{/if}
			{/if}
		</div>

		<!-- Recent Activity - hide untuk VIEWER -->
		{#if hasAnyRole(['SUPERADMIN', 'TENANT_ADMIN', 'HR', 'FINANCE'])}
		<div class="card bg-base-100 shadow-lg">
			<div class="card-body">
				<h2 class="card-title text-base-content">Aktivitas Terbaru</h2>
				<div class="overflow-x-auto">
					<table class="table table-zebra">
						<thead>
							<tr>
								<th class="text-base-content">Waktu</th>
								<th class="text-base-content">User</th>
								<th class="text-base-content">Aksi</th>
								<th class="text-base-content">Tabel</th>
								<th class="text-base-content">Record ID</th>
							</tr>
						</thead>
						<tbody>
							{#if recentActivities.length > 0}
								{#each recentActivities as activity}
									{@const actionInfo = getActionInfo(activity.action)}
									<tr>
										<td class="text-base-content opacity-70">
											{new Date(activity.created_at).toLocaleString('id-ID')}
										</td>
										<td class="text-base-content">{activity.user?.name || activity.user?.email || 'System'}</td>
										<td>
											<span class="badge {actionInfo.badgeClass}">
												{actionInfo.label}
											</span>
										</td>
										<td class="text-base-content opacity-70">{activity.table_name}</td>
										<td class="text-base-content opacity-70 text-sm">{activity.record_id || '-'}</td>
									</tr>
								{/each}
							{:else}
								<tr>
									<td colspan="5" class="text-center text-base-content opacity-50 py-8">
										Tidak ada aktivitas terbaru
									</td>
								</tr>
							{/if}
						</tbody>
					</table>
				</div>
			</div>
		</div>
		{/if}
	{/if}
</div>

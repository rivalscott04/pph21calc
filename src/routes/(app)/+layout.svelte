<script>
	// Layout dengan sidebar untuk aplikasi
</script>

<div class="drawer lg:drawer-open">
	<input id="app-drawer" type="checkbox" class="drawer-toggle" />
	<div class="drawer-content flex flex-col">
		<!-- Topbar -->
		<label for="app-drawer" class="btn btn-ghost lg:hidden m-2">☰</label>
		<main class="p-4">
			<slot />
		</main>
	</div>
	<div class="drawer-side">
		<label for="app-drawer" class="drawer-overlay"></label>
		<aside class="menu p-4 w-72 bg-base-200 min-h-full">
			<h2 class="text-xl font-bold mb-4">PPH21 System</h2>
			<ul class="menu gap-1">
				<li><a href="/dashboard">Dashboard</a></li>
				<li><a href="/payroll">Payroll</a></li>
				<li><a href="/calculator">Kalkulator</a></li>
				<li><a href="/coretax">CoreTax Export</a></li>
				<li class="menu-title">Settings</li>
				<li><a href="/settings/branding">Branding & Tema</a></li>
				<li><a href="/settings/id-schemes">Skema ID</a></li>
				<li><a href="/settings/modules">Modul</a></li>
				<li><a href="/settings/users">User & Role</a></li>
			</ul>
		</aside>
	</div>
</div>


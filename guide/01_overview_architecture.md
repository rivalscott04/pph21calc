# 01. Overview & Arsitektur Sistem PPh 21 (Multi-tenant, CoreTax, SvelteKit + daisyUI)

Dokumen ini adalah ringkasan utama sistem PPh 21 yang akan dipakai lintas sektor
(bank, BUMN, kampus, instansi pemerintah), dengan arsitektur:

- **Multi-tenant** (banyak perusahaan/bank/instansi dalam satu sistem)
- **Superadmin** yang mengelola seluruh tenant
- **Frontend**: SvelteKit + TailwindCSS + daisyUI (layout sidebar)
- **Backend**: Laravel 11 (REST API)
- **Pajak**: PPh 21 sesuai regulasi terbaru (TER, PMK 168/2023, natura, dll)
- **Integrasi**: CoreTax (pengganti DJP Online) untuk BPA1/BPA2

---

## 1. Layer Utama

1. **Frontend (SvelteKit + daisyUI)**
   - SPA/MPA dengan routing SvelteKit
   - UI kit daisyUI: komponen `btn`, `navbar`, `drawer`, `menu`, dll.
   - Layout standar: **sidebar** di kiri, konten di kanan
   - Tema `brand` yang bisa dikonfigurasi (warna primary/secondary/accents)
   - Modul frontend: Dashboard, Payroll, Kalkulator, CoreTax, Settings (Branding, ID, Modules)

2. **Backend (Laravel 11)**
   - Menyediakan REST API untuk:
     - Autentikasi & otorisasi (Superadmin, Tenant Admin, HR, Finance, Viewer)
     - Manajemen tenant & user
     - Master pegawai & struktur organisasi
     - Penghitungan PPh 21 & slip gaji
     - Ekspor & unggah BPA1/BPA2 ke CoreTax
     - Config modul & branding
     - Log audit & activity

3. **Database (MySQL/MariaDB)**
   - Skema multi-tenant berbasis `tenants` dan `tenant_id` di data bisnis
   - Master orang (persons) + skema ID modular
   - Payroll, referensi pajak, log CoreTax, log audit, konfigurasi

4. **Infra**
   - Queue: Redis + Laravel Horizon
   - Storage: file bukti potong, ekspor JSON/CSV, lampiran
   - Monitoring & logging: Laravel log + eksternal (ELK/Grafana bila perlu)

---

## 2. Flow Tingkat Tinggi

1. **Superadmin**
   - Membuat tenant baru (perusahaan/bank/kampus)
   - Mengatur modul apa saja yang aktif per tenant
   - Mengatur batasan & konfigurasi global (misal: integrasi CoreTax, kebijakan keamanan)

2. **Tenant Admin**
   - Mengelola unit organisasi, pegawai, skema ID internal
   - Mengatur branding tema (warna primary, secondary, dll)
   - Mengelola role user dalam tenant (HR, Finance, Viewer)

3. **HR / Payroll Officer**
   - Mengisi data penghasilan (earnings) + potongan (deductions)
   - Menjalankan proses payroll per periode
   - Mencetak slip & laporan PPh 21

4. **Finance / Tax Officer**
   - Review hasil PPh 21
   - Menjalankan approval
   - Mengekspor BPA1/BPA2 & mengunggah ke CoreTax

5. **Viewer / Auditor**
   - Membaca laporan & log sesuai izin
   - Mengunduh data untuk audit internal/eksternal

---

## 3. Layout Frontend (Sidebar)

Layout dasar menggunakan komponen **drawer** daisyUI:

```svelte
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
      <ul class="menu">
        <li><a href="/dashboard">Dashboard</a></li>
        <li><a href="/payroll">Payroll</a></li>
        <li><a href="/calculator">Kalkulator</a></li>
        <li><a href="/coretax">CoreTax Export</a></li>
        <li><a href="/settings/branding">Branding & Tema</a></li>
        <li><a href="/settings/id-schemes">Skema ID</a></li>
        <li><a href="/settings/modules">Modul & Feature Flag</a></li>
        <li><a href="/settings/users">User & Role</a></li>
      </ul>
    </aside>
  </div>
</div>
```

---

## 4. Dokumentasi Lain

Paket dokumentasi ini terdiri dari beberapa file:
- `01_overview_architecture.md` (dokumen ini)
- `02_roles_multitenancy.md`
- `03_database_schema.md`
- `04_api_endpoints.md`
- `05_pph21_calculation.md`
- `06_modules_feature_flags.md`
- `07_frontend_svelte_sidebar.md`
- `08_frontend_dynamic_id.md`
- `09_svelte_daisyui_branding.md`
- `10_security_compliance.md`

Masing-masing file membahas topik spesifik, saling melengkapi sebagai dokumen teknis utama sistem.

# 02. Role & Multi-tenancy

Dokumen ini menjelaskan model multi-tenant dan role/akses di sistem.

---

## 1. Konsep Multi-tenant

- **Tenant** = satu entitas organisasi (Bank NTB Syariah, BUMN X, Kampus Y, dll).
- Setiap tenant memiliki:
  - Struktur organisasi sendiri (org units)
  - Pegawai, payroll, konfigurasi pajak/branding sendiri
  - Modul yang dapat berbeda-beda (misal bank aktifkan compliance_ojk, kampus tidak)
- Data harus **terisolasi antar tenant**.

### Tabel Kunci
- `tenants`
- `tenant_users` / `users`
- Semua tabel bisnis utama memiliki `tenant_id`:
  - `entities`, `org_units`, `employments`, `payroll_subjects`
  - `periods`, `earnings`, `payroll_calculations`
  - `config_branding`, `config_modules`, dll.

---

## 2. Role Utama

### 2.1 Superadmin (Global)
- Tidak terikat satu tenant tertentu.
- Akses:
  - CRUD tenant
  - Lihat semua tenant & billing (kalau nanti SaaS)
  - Atur konfigurasi global (mis: integrasi CoreTax, batasan keamanan)
  - Reset password tenant admin
  - Monitor log global

### 2.2 Tenant Admin
- Terikat pada satu tenant.
- Akses:
  - Kelola struktur organisasi tenant
  - Kelola data pegawai
  - Kelola user-role di tenant (HR, Finance, Viewer)
  - Kelola branding, modul, dan konfigurasi pajak lokal
  - Lihat laporan umum untuk tenant tersebut

### 2.3 HR / Payroll Officer
- Terikat pada tenant.
- Akses:
  - Input & impor penghasilan (earnings) dan potongan
  - Menjalankan proses hitung payroll per periode (preview)
  - Menghasilkan slip gaji pegawai
  - Tidak bisa mengubah konfigurasi Modul/Branding (kecuali diberi hak khusus)

### 2.4 Finance / Tax Officer
- Terikat pada tenant.
- Akses:
  - Review hasil perhitungan PPh 21
  - Approval periode payroll (maker-checker)
  - Ekspor BPA1/BPA2 dan kirim ke CoreTax
  - Lihat log CoreTax & hasil validasi

### 2.5 Viewer / Auditor
- Terikat pada tenant.
- Akses:
  - Read-only terhadap laporan, payroll, dan beberapa konfigurasi
  - Unduh laporan untuk kebutuhan audit

---

## 3. Tabel User & Role (Model)

### 3.1 users
- `id`
- `email`
- `password_hash`
- `is_superadmin` (bool)
- `name`
- `status` (active/inactive)
- timestamp

### 3.2 tenant_users
- `id`
- `user_id` FK
- `tenant_id` FK
- `role` (TENANT_ADMIN/HR/FINANCE/VIEWER)
- `status` (active/inactive)

---

## 4. Enforcement di Backend

- Middleware `auth` + `tenant_scope` akan memastikan:
  - Jika `is_superadmin = true` → boleh akses endpoint global (tenant listing, config global)
  - Jika bukan superadmin:
    - Wajib memilih `tenant_id` aktif (via subdomain atau header)
    - Semua query data harus difilter `tenant_id` = tenant aktif
- Per endpoint harus men-check role:
  - Misal: `POST /payroll/{period}/commit` → hanya HR dan FINANCE tertentu yang boleh

---

## 5. Support SaaS

dengan struktur di atas, sistem bisa dijadikan SaaS:
- Setiap perusahaan/instansi daftar sebagai **tenant**.
- Superadmin mengelola licensing & provisioning.
- Tenant Admin mengelola user di organisasinya.

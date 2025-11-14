# Overview Dokumen Teknis - Sistem PPh 21 Multi-tenant

## Ringkasan Arsitektur

Sistem PPh 21 multi-tenant dengan:
- **Backend**: Laravel 11 (REST API) di folder `backend/`
- **Frontend**: SvelteKit + TailwindCSS + daisyUI di root folder
- **Database**: MySQL/MariaDB dengan skema multi-tenant
- **Fitur Utama**: Multi-tenant, RBAC, Payroll & PPh 21, CoreTax integration, Branding dinamis, ID schemes dinamis

---

## Mapping Dokumen ke Implementasi

### 01. Overview & Architecture (`01_overview_architecture.md`)
**Fokus**: Arsitektur sistem secara keseluruhan
- Layer: Frontend (SvelteKit), Backend (Laravel), Database, Infra
- Flow: Superadmin → Tenant Admin → HR/Finance → Viewer
- Layout sidebar menggunakan daisyUI drawer

**Implementasi**:
- Setup struktur project (backend + frontend)
- Layout sidebar dengan drawer daisyUI
- Routing dasar

---

### 02. Roles & Multi-tenancy (`02_roles_multitenancy.md`)
**Fokus**: Model multi-tenant dan sistem role/akses
- Konsep tenant (isolasi data)
- Role: Superadmin, Tenant Admin, HR, Finance, Viewer
- Tabel: `tenants`, `users`, `tenant_users`
- Enforcement: middleware `auth` + `tenant_scope`

**Implementasi**:
- Migration: `tenants`, `users`, `tenant_users`
- Middleware: `auth`, `tenant_scope`, `role_check`
- Logic superadmin vs tenant user
- Query scoping dengan `tenant_id`

---

### 03. Database Schema (`03_database_schema.md`)
**Fokus**: Skema database lengkap
- Multi-tenant & User (3 tabel)
- Master Orang & Identitas (3 tabel)
- Organisasi & Employment (3 tabel)
- Payroll & Pajak (6 tabel)
- CoreTax & Audit (2 tabel)
- Konfigurasi (2 tabel)

**Implementasi**:
- 19 migration files
- Model Eloquent dengan trait `TenantScoped`
- Relationships antar model
- Indexes untuk performa

---

### 04. API Endpoints (`04_api_endpoints.md`)
**Fokus**: Spesifikasi REST API
- 8 grup endpoint: Auth, Config, Master Data, Payroll, Kalkulator, CoreTax, Audit, Dashboard
- Total ~40+ endpoints

**Implementasi**:
- Controllers untuk setiap grup
- Routes dengan middleware protection
- Request validation
- Response formatting
- Error handling

---

### 05. PPh 21 Calculation (`05_pph21_calculation.md`)
**Fokus**: Algoritma perhitungan PPh 21
- Input: status PTKP, penghasilan, potongan
- Langkah: Bruto → Biaya Jabatan → Neto → TER → PPh 21
- TER (PMK 168/2023) untuk Jan-Nov
- Rekonsiliasi tahunan untuk Desember
- Natura & fasilitas

**Implementasi**:
- Service class `PPh21Calculator`
- Method untuk setiap langkah perhitungan
- Unit tests untuk validasi algoritma
- Integration dengan payroll process

---

### 06. Modules & Feature Flags (`06_modules_feature_flags.md`)
**Fokus**: Sistem modular dengan feature flags
- 7 modul: core_payroll, coretax_integration, compliance_ojk, dll
- Tabel `config_modules` per tenant
- Frontend guard berdasarkan feature flags

**Implementasi**:
- Migration `config_modules`
- API endpoint `GET /config/modules`
- Frontend store `featureFlags`
- Route guards & menu visibility

---

### 07. Frontend Sidebar (`07_frontend_svelte_sidebar.md`)
**Fokus**: Layout sidebar dengan daisyUI
- Struktur project SvelteKit
- Layout global & sidebar
- Komponen drawer + menu

**Implementasi**:
- `src/routes/+layout.svelte` (global)
- `src/routes/(app)/+layout.svelte` (sidebar)
- Menu navigation dengan feature flag guards
- Responsive drawer (mobile/desktop)

---

### 08. Dynamic ID Validation (`08_frontend_dynamic_id.md`)
**Fokus**: Validasi ID pegawai yang config-driven
- Skema ID dari backend (`identifier_schemes`)
- Validasi dinamis berdasarkan regex, length, normalize
- Uniqueness check dengan scope

**Implementasi**:
- API endpoint `GET /config/identifier-schemes`
- Frontend validator (Zod/Svelte) dinamis
- Form input dengan real-time validation
- Uniqueness check via API

---

### 09. Branding (`09_svelte_daisyui_branding.md`)
**Fokus**: Branding warna dinamis per tenant
- Tema `brand` di daisyUI
- CSS variables untuk warna
- Store tema Svelte
- Halaman konfigurasi branding

**Implementasi**:
- Tailwind config dengan tema `brand`
- Store `brandColors` di Svelte
- Fungsi `applyBrandTheme()` untuk update CSS variables
- Halaman `/settings/branding` dengan preview

---

### 10. Security & Compliance (`10_security_compliance.md`)
**Fokus**: Keamanan & kepatuhan
- Prinsip: Confidentiality, Integrity, Availability
- RBAC, Audit Trail, Data Protection
- Integrasi CoreTax yang aman
- Backup & DR

**Implementasi**:
- Password policy, MFA (opsional)
- Activity logging middleware
- Data encryption (field/disk level)
- HTTPS/TLS enforcement
- Masking di UI untuk data sensitif

---

## Urutan Implementasi yang Disarankan

### Phase 1: Foundation (Setup & Database)
1. Setup Laravel 11 backend
2. Setup SvelteKit frontend
3. Buat semua migration (19 tabel)
4. Setup authentication dasar

### Phase 2: Core Multi-tenant
5. Implementasi multi-tenancy middleware
6. Implementasi RBAC
7. API Auth & Tenant management
8. Frontend authentication flow

### Phase 3: Master Data
9. API Master Data (persons, org-units, employments)
10. Frontend halaman Master Data
11. Dynamic ID validation
12. Branding & modules config

### Phase 4: Payroll & PPh 21
13. PPh 21 Calculator service
14. API Payroll endpoints
15. Frontend halaman Payroll
16. Kalkulator mandiri

### Phase 5: Advanced Features
17. CoreTax integration
18. Dashboard & reporting
19. Audit trail
20. Security hardening

---

## Catatan Penting

1. **Root Project**: Folder Svelte frontend adalah root, backend ada di `backend/`
2. **Laravel Version**: Menggunakan Laravel 11
3. **Isolasi Data**: Semua query harus di-scope dengan `tenant_id` (kecuali superadmin)
4. **Feature Flags**: Modul harus bisa diaktif/nonaktif per tenant
5. **Branding**: Warna tema harus bisa dikonfigurasi per tenant
6. **ID Schemes**: Format ID pegawai harus config-driven, tidak hardcode

---

## Dependencies & Tools

### Backend
- Laravel 11
- Laravel Sanctum (untuk API auth)
- Database: MySQL/MariaDB
- Queue: Redis (opsional, untuk async jobs)

### Frontend
- SvelteKit
- TailwindCSS 4
- daisyUI 5
- Axios/Fetch untuk API calls

---

## Next Steps

Lihat TODO list untuk detail task yang perlu dikerjakan. Mulai dari Phase 1 (Foundation) dan lanjutkan secara bertahap.


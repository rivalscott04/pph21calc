# 03. Database Schema (Ringkasan)

Skema di bawah ini adalah rangkuman; implementasi detail dapat diturunkan ke migration Laravel.

---

## 1. Multi-tenant & User

### tenants
- `id`
- `code`
- `name`
- `npwp_pemotong` (jika tenant = entitas pemotong)
- `status` (active/inactive)
- timestamps

### users
- `id`
- `email`
- `password`
- `name`
- `is_superadmin` (bool)
- `status`
- timestamps

### tenant_users
- `id`
- `user_id` FK → users
- `tenant_id` FK → tenants
- `role` (TENANT_ADMIN/HR/FINANCE/VIEWER)
- `status`
- timestamps

---

## 2. Master Orang & Identitas

### persons
- `id` (UUID)
- `tenant_id` FK → tenants
- `full_name`
- `nik`
- `npwp`
- `birth_date`
- timestamps

### identifier_schemes
- `id`
- `tenant_id` FK (jika skema per-tenant)
- `code` (BANK_EMP_ID, BUMN_9D, dst)
- `label`
- `entity_type` (BANK/BUMN/KAMPUS/LAINNYA)
- `regex_pattern`
- `length_min`, `length_max`
- `normalize_rule` (NUMERIC/ALNUM/UPPER/NONE)
- `example`
- `checksum_type` (LUHN/MOD_N/NONE)
- timestamps

### person_identifiers
- `id`
- `tenant_id` FK
- `person_id` FK → persons
- `scheme_id` FK → identifier_schemes
- `raw_value`
- `norm_value`
- `scope_entity_id` (opsional)
- `scope_org_unit_id` (opsional)
- `effective_start`, `effective_end`
- `is_primary`
- UNIQUE `(tenant_id, scheme_id, norm_value, scope_entity_id)`

---

## 3. Organisasi & Hubungan Kerja

### org_units
- `id`
- `tenant_id` FK
- `code`
- `name`
- `type` (HQ/REGION/BRANCH/KCP/UNIT)
- `parent_id` (tree)
- timestamps

### employments
- `id`
- `tenant_id` FK
- `person_id` FK
- `org_unit_id` FK
- `employment_type` (tetap/tidak_tetap/harian/tenaga_ahli)
- `start_date`, `end_date`
- `primary_payroll` (bool)
- timestamps

### payroll_subjects
- `id`
- `tenant_id`
- `employment_id` FK
- `ptkp_code`
- `has_npwp`
- `tax_profile_json`
- `active`
- timestamps

---

## 4. Payroll & Pajak

### periods
- `id`
- `tenant_id`
- `year`
- `month`
- `status` (draft/reviewed/approved/posted)
- timestamps

### components
- `id`
- `tenant_id`
- `code`
- `name`
- `group` (gaji_pokok/tunjangan/bonus/lembur/natura/lainnya)
- `taxable` (bool)
- `notes`
- timestamps

### earnings
- `id`
- `tenant_id`
- `employment_id` FK
- `period_id` FK
- `component_id` FK
- `amount`
- `meta` JSON
- timestamps

### deductions_manual
- `id`
- `tenant_id`
- `employment_id` FK
- `period_id` FK
- `type` (iuran_pensiun/zakat/lainnya)
- `amount`
- timestamps

### payroll_calculations
- `id`
- `tenant_id`
- `employment_id` FK
- `period_id` FK
- `bruto`
- `biaya_jabatan`
- `iuran_pensiun`
- `zakat`
- `neto_masa`
- `ptkp_yearly`
- `pkp_annualized`
- `pph21_masa`
- `pph21_ytd`
- `pph21_settlement_dec`
- timestamps

---

## 5. CoreTax & Audit

### coretax_logs
- `id`
- `tenant_id`
- `period_id` FK
- `payload_json`
- `status` (pending/sent/validated/failed)
- `ref_no`
- `response_json`
- timestamps

### activity_logs
- `id`
- `tenant_id`
- `user_id`
- `table_name`
- `before` JSON
- `after` JSON
- `action` (insert/update/delete)
- timestamps

---

## 6. Konfigurasi

### config_branding
- `id`
- `tenant_id`
- `primary`
- `secondary`
- `accent`
- `neutral`
- `base100`
- timestamps

### config_modules
- `id`
- `tenant_id`
- `core_payroll` (bool)
- `coretax_integration` (bool)
- `compliance_ojk` (bool)
- `compliance_pdp` (bool)
- `audit_trail` (bool)
- `bpjs_integration` (bool)
- `syariah_extension` (bool)
- timestamps

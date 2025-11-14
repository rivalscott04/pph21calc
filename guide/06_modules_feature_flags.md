# 06. Modul & Feature Flags

Sistem dirancang modular sehingga fitur bisa diaktif/nonaktif per tenant.

---

## 1. Daftar Modul

- `core_payroll` — modul utama PPh 21 & payroll
- `coretax_integration` — ekspor & upload BPA1/BPA2 ke CoreTax
- `compliance_ojk` — kontrol kepatuhan sektor keuangan
- `compliance_pdp` — modul perlindungan data pribadi
- `audit_trail` — log immutable untuk audit
- `bpjs_integration` — hitung & sinkron iuran BPJS
- `syariah_extension` — fitur zakat/infaq/sedekah untuk lembaga syariah

---

## 2. Tabel config_modules

- `tenant_id`
- Field boolean untuk tiap modul

Contoh isi:
- Bank NTB Syariah: semua modul aktif.
- Kampus: `core_payroll`, `bpjs_integration` saja.

---

## 3. Frontend Feature Flags

- Frontend memanggil `GET /config/modules`
- Menyimpan ke store Svelte (mis. `featureFlags`)
- Sidebar dan halaman modul dijaga dengan guard:
  - Jika modul OFF → menu disembunyikan, route diblok.

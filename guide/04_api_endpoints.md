# 04. API Endpoints (Ringkasan)

---

## 1. Auth & Tenant

- `POST /auth/login`
- `POST /auth/logout`
- `GET /auth/me`

### Superadmin
- `GET /tenants`
- `POST /tenants`
- `GET /tenants/{id}`
- `PATCH /tenants/{id}`
- `GET /tenants/{id}/users`
- `POST /tenants/{id}/users`

---

## 2. Config & Modules

- `GET /config/modules` (per tenant)
- `PATCH /config/modules`
- `GET /config/branding`
- `PATCH /config/branding`
- `GET /config/identifier-schemes`
  - query: `?entity=...` (opsional)

---

## 3. Pegawai & ID

- `GET /persons`
- `POST /persons`
- `GET /persons/{id}`
- `GET /persons/resolve?q=...`
- `POST /persons/{id}/identifiers`
- `GET /identifiers/check-unique`

---

## 4. Organisasi & Employment

- `GET /org-units`
- `POST /org-units`
- `GET /components`
- `POST /components`
- `GET /components/{id}`
- `PATCH /components/{id}`
- `GET /employments`
- `POST /employments`
- `GET /payroll-subjects`
- `POST /payroll-subjects`

---

## 5. Payroll

- `GET /periods`
- `POST /periods`
- `PATCH /periods/{id}/status` (draft → reviewed → approved → posted)

- `GET /earnings?period=...`
- `POST /earnings`
- `GET /deductions?period=...`
- `POST /deductions`

- `POST /payroll/{period}/preview`
- `POST /payroll/{period}/commit`
- `GET /payroll/{period}/summary`
- `GET /payroll/{period}/slip/{employment}`

---

## 6. Kalkulator Mandiri

- `POST /calculator/pph21`
  - Input: status PTKP, penghasilan & potongan
  - Output: bruto, neto, PKP, PPh21 masa, notes

---

## 7. CoreTax Integration

- `POST /coretax/export` — generate JSON BPA1/BPA2
- `POST /coretax/upload` — kirim ke CoreTax
- `GET /coretax/logs`
- `GET /coretax/logs/{id}`

---

## 8. Audit & Dashboard

- `GET /logs/activity`
- `POST /logs/filter`

- `GET /dashboard/summary`
- `GET /dashboard/chart`

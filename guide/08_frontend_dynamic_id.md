# 08. Frontend Dynamic ID Validation (Config-driven)

Tujuan: mendukung format ID pegawai yang berbeda-beda (Bank, BUMN, Kampus, dll)
tanpa perlu hardcode panjang 6/8/9 di kode frontend.

---

## 1. Konfigurasi Skema ID dari Backend

Endpoint: `GET /config/identifier-schemes?entity=BANK_NTB`

Contoh respons:
```json
{
  "entity": "BANK_NTB",
  "schemes": [
    {
      "code": "BANK_EMP_ID",
      "label": "ID Pegawai Bank",
      "normalize": "NUMERIC",
      "patterns": ["^[0-9]{8}$"],
      "length": { "min": 8, "max": 8 },
      "example": "00123456"
    },
    {
      "code": "KAMPUS_STAF",
      "label": "ID Staf Kampus",
      "normalize": "ALNUM",
      "patterns": ["^[A-Z]{2}[0-9]{4}$"],
      "example": "CS1234"
    }
  ]
}
```

---

## 2. Pola Validator di Frontend

- Ambil skema via API.
- Bangun schema Zod/Svelte validator berdasarkan field:
  - `normalize` (NUMERIC/ALNUM/UPPER)
  - `patterns` (regex)
  - `length` (min/max)
- Validasi dilakukan sebelum kirim ke backend.

---

## 3. Uniqueness & Scope

- Untuk cek ID sudah dipakai atau belum:
  - Frontend panggil `GET /identifiers/check-unique`
  - Backend men-check ke tabel `person_identifiers` dengan kombinasi `(tenant_id, scheme_id, norm_value, scope_entity_id)`.

Dengan cara ini, format ID benar-benar **config-driven**.

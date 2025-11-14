# 10. Security & Compliance (Ringkas)

Dokumen ini meng-highlight poin keamanan & kepatuhan yang harus didukung
terutama jika dipakai di sektor keuangan / bank.

---

## 1. Prinsip Utama

- **Confidentiality**: data gaji & pajak pegawai sangat sensitif.
- **Integrity**: perhitungan PPh 21 harus akurat dan dapat diaudit.
- **Availability**: sistem harus dapat diakses sesuai SLA.

---

## 2. Kontrol Akun & Akses

- Autentikasi kuat (password policy, MFA untuk admin)
- Role-based Access Control (RBAC) seperti dijelaskan di `02_roles_multitenancy.md`
- Superadmin hanya digunakan untuk:
  - manajemen tenant
  - konfigurasi global
  - operasi level platform

---

## 3. Audit Trail

- Setiap perubahan data penting dicatat di `activity_logs`:
  - siapa (user_id)
  - apa yang diubah (table_name, before, after)
  - kapan (timestamp)
- Log harus bersifat append-only (tidak dihapus kecuali lewat prosedur resmi).

---

## 4. Perlindungan Data

- Data sensitif (NIK, NPWP, gaji) dapat dienkripsi di level field atau disk.
- Enkripsi in transit dengan HTTPS/TLS.
- Masking di UI untuk data tertentu (misal hanya 4 digit terakhir).

---

## 5. Integrasi CoreTax

- Data yang dikirim ke CoreTax harus sesuai spesifikasi format.
- Simpan payload dan respons CoreTax di `coretax_logs` untuk eviden.
- Gunakan kredensial & endpoint yang aman (config di environment, bukan di kode).

---

## 6. Backup & DR

- Backup database terjadwal.
- Uji restore berkala.
- Rencana DR (Disaster Recovery) dengan RPO/RTO yang disepakati.

---

Dokumen ini bisa dikembangkan lagi menjadi standar implementasi
keamanan dan kepatuhan detail sesuai kebijakan internal masing-masing organisasi.

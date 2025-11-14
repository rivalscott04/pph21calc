# 05. Algoritme Perhitungan PPh 21

Ringkasan langkah perhitungan (detail bisa dikodekan pada service `PPh21Calculator`).

---

## 1. Data Input

- Status pegawai (tetap / tidak tetap / harian / tenaga ahli)
- Status PTKP (TK0, K0, K1, K2, K3, dst)
- Komponen penghasilan (gaji_pokok, tunjangan, bonus, lembur, natura)
- Pengurang (biaya jabatan, iuran pensiun, zakat)
- Data historis masa sebelumnya (YTD) jika diperlukan

---

## 2. Langkah Dasar

1. **Hitung Penghasilan Bruto**
   - `bruto = sum(komponen_penghasilan_taxable)`
2. **Biaya Jabatan & Pengurang**
   - `biaya_jabatan = min(5% * bruto, 500.000/bln atau 6 jt/thn)`
   - `iuran_pensiun = min(5% * bruto, 200.000/bln atau 2,4 jt/thn)`
   - `zakat` sesuai ketentuan (lembaga amil resmi)
3. **Neto Masa**
   - `neto_masa = bruto - (biaya_jabatan + iuran_pensiun + zakat)`

---

## 3. Penggunaan TER (PMK 168/2023)

- Untuk pegawai tetap, masa Januari–November:
  - Gunakan **TER (Tarif Efektif Rata-rata)** bulanan/harian sesuai tabel.
  - `pph21_masa = neto_masa * ter_rate`
- Desember / PHK:
  - Lakukan rekonsiliasi tahunan (lihat langkah 4).

---

## 4. Perhitungan Tahunan (Desember / PHK)

1. Hitung neto setahun (annualisasi jika perlu).
2. `pkp_yearly = neto_setahun - ptkp`
3. Terapkan tarif Pasal 17:
   - Lapisan 1: 0–60 jt → 5%
   - Lapisan 2: 60–250 jt → 15%
   - Lapisan 3: 250–500 jt → 25%
   - Lapisan 4: 500 jt–5 M → 30%
   - Lapisan 5: >5 M → 35%
4. `pph21_tahunan = tarif_progresif(pkp_yearly)`
5. Bandingkan dengan PPh 21 yang sudah dipotong selama tahun berjalan (`pph21_ytd`)
   - Selisih dibebankan pada masa Desember.

---

## 5. Natura & Fasilitas

- Natura tertentu **dikecualikan** (mis. konsumsi di kantor, fasilitas ibadah, dll).
- Natura lain menjadi objek PPh 21 berdasarkan nilai pasar atau dasar pengenaan yang diatur.

Implementasi:
- Tabel `components` diberi flag `is_natura` + `natura_rule_id`
- Tabel `natura_rules` berisi jenis natura dan status (exempt / taxable)

---

## 6. Output yang Disimpan

Di tabel `payroll_calculations` disimpan:
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

Data ini digunakan untuk:
- Slip gaji
- Laporan internal
- Ekspor CoreTax (BPA1/BPA2)

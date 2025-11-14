# 09. SvelteKit + daisyUI Branding (Config-driven)

Dokumen ini menjelaskan implementasi branding warna dinamis untuk frontend.

---

## 1. Setup Tailwind + daisyUI (SvelteKit)

- Install Tailwind & daisyUI mengikuti dokumentasi resmi.
- Di `tailwind.config.cjs` aktifkan plugin daisyUI dan definisikan theme `brand`.

Contoh:
```js
export default {
  content: ['./src/**/*.{html,js,svelte,ts}'],
  theme: { extend: {} },
  plugins: [require('daisyui')],
  daisyui: {
    themes: [
      'light',
      'dark',
      {
        brand: {
          'primary': '#0ea5e9',
          'secondary': '#10b981',
          'accent': '#f59e0b',
          'neutral': '#3d4451',
          'base-100': '#ffffff'
        }
      }
    ]
  }
}
```

---

## 2. CSS Variables Tema `brand`

Pada `src/app.css`:
```css
:root[data-theme="brand"] {
  --p: 210 92% 56%;
  --s: 160 84% 39%;
  --a: 42 96% 56%;
  --n: 215 16% 27%;
  --b1: 0 0% 100%;
}
```

Nilai HSL ini akan dioverride runtime berdasarkan input HEX dari admin.

---

## 3. Store Tema Svelte

- Store `brandColors` menyimpan nilai HEX (primary, secondary, accent, neutral, base100).
- Fungsi `applyBrandTheme()` mengubah CSS variable `--p`, `--s`, `--a`, `--n`, `--b1`.
- Data bisa diambil dari backend (`/config/branding`) dan disimpan di localStorage.

---

## 4. Halaman Branding & Preview

- Route: `/settings/branding`
- Fitur:
  - Input HEX untuk tiap warna (dengan validasi sederhana `isHex()`)
  - Button “Simpan & Terapkan” yang memanggil backend dan memanggil `applyBrandTheme()`
  - Preview navbar + beberapa tombol & kartu agar user melihat hasilnya secara langsung.

Dengan ini, branding UI sistem sepenuhnya **config-driven** dan bisa disesuaikan
per tenant (perusahaan/bank/instansi).

# Environment Variables Setup

Dokumentasi ini menjelaskan cara setup environment variables untuk development dan production.

## Frontend (SvelteKit)

### Development (.env)

Buat file `.env` di root project (sama level dengan `package.json`):

```env
# Untuk development, gunakan absolute URL karena frontend dan backend di port berbeda
VITE_API_URL=http://localhost:8000/api
```

### Production (.env.production)

Buat file `.env.production` di root project:

```env
# Untuk production, gunakan relative URL jika frontend dan backend di domain yang sama
VITE_API_URL=/api

# Atau jika backend di subdomain berbeda:
# VITE_API_URL=https://api.example.com/api
```

### Cara Kerja

1. **Relative URL** (`/api` atau empty string):
   - Digunakan ketika frontend dan backend di domain yang sama
   - Menghindari CORS issues
   - Cocok untuk production dengan reverse proxy (nginx, Apache)

2. **Absolute URL** (`http://localhost:8000/api`):
   - Digunakan ketika frontend dan backend di port/domain berbeda
   - Cocok untuk development
   - Juga bisa digunakan jika backend di subdomain berbeda

### Build Command

SvelteKit/Vite akan otomatis menggunakan `.env.production` saat build:

```bash
# Development
npm run dev

# Production build
npm run build
```

## Backend (Laravel)

### Sanctum Configuration

Backend sudah support relative URLs melalui Laravel Sanctum. Konfigurasi ada di `backend/config/sanctum.php`:

```php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', ...))
```

### Environment Variables

Buat file `.env` di folder `backend/`:

```env
APP_NAME="PPH21 Calculator"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Sanctum Stateful Domains (untuk same-origin requests)
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:5173,127.0.0.1,127.0.0.1:8000

# Database, dll...
```

Untuk production, update `.env`:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

# Tambahkan domain production
SANCTUM_STATEFUL_DOMAINS=yourdomain.com,www.yourdomain.com
```

## Deployment Scenarios

### Scenario 1: Same Domain (Recommended for Production)

**Frontend**: `https://example.com`  
**Backend**: `https://example.com/api`

**Frontend `.env.production`**:
```env
VITE_API_URL=/api
```

**Backend `.env`**:
```env
APP_URL=https://example.com
SANCTUM_STATEFUL_DOMAINS=example.com,www.example.com
```

### Scenario 2: Different Subdomains

**Frontend**: `https://app.example.com`  
**Backend**: `https://api.example.com`

**Frontend `.env.production`**:
```env
VITE_API_URL=https://api.example.com/api
```

**Backend `.env`**:
```env
APP_URL=https://api.example.com
SANCTUM_STATEFUL_DOMAINS=app.example.com
```

### Scenario 3: Development

**Frontend**: `http://localhost:5173`  
**Backend**: `http://localhost:8000`

**Frontend `.env`**:
```env
VITE_API_URL=http://localhost:8000/api
```

**Backend `.env`**:
```env
APP_URL=http://localhost:8000
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:5173,127.0.0.1,127.0.0.1:8000
```

## Testing

Setelah setup, test dengan:

1. **Development**: 
   ```bash
   npm run dev
   # Frontend akan menggunakan VITE_API_URL dari .env
   ```

2. **Production Build**:
   ```bash
   npm run build
   # Build akan menggunakan VITE_API_URL dari .env.production
   ```

3. **Check API calls**:
   - Buka browser DevTools → Network tab
   - Lihat apakah API calls menggunakan URL yang benar
   - Relative URL: `/api/calculator/pph21`
   - Absolute URL: `http://localhost:8000/api/calculator/pph21`

## Notes

- File `.env` dan `.env.production` tidak di-commit ke git (sudah di `.gitignore`)
- Pastikan file `.env.production` ada sebelum build production
- Relative URLs lebih aman dan performant untuk production
- Backend Laravel Sanctum sudah support same-origin requests secara default


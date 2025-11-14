# Multi-Tenancy Implementation

## Overview

Sistem menggunakan middleware `TenantScope` untuk mengisolasi data antar tenant. Setiap tenant memiliki data yang terpisah dan tidak bisa saling akses.

## Middleware: TenantScope

### Fungsi
- **Superadmin**: Bisa akses semua data (tidak di-scope)
- **Tenant User**: Wajib memiliki tenant context, semua query di-filter berdasarkan `tenant_id`

### Cara Kerja
1. Cek apakah user adalah superadmin → skip tenant scoping
2. Jika bukan superadmin:
   - Ambil `tenant_id` dari header `X-Tenant-ID` atau request input
   - Jika tidak ada, ambil dari user's active tenant
   - Verify user punya akses ke tenant tersebut
   - Set tenant context ke request

### Error Responses
- `403 TENANT_REQUIRED`: User non-superadmin tidak punya tenant context
- `403 TENANT_ACCESS_DENIED`: User tidak punya akses ke tenant yang diminta

## Trait: HasTenantScope

### Penggunaan di Model

```php
use App\Traits\HasTenantScope;

class Person extends Model
{
    use HasTenantScope;
    
    // Model akan otomatis di-filter berdasarkan tenant_id
}
```

### Methods

- `scopeForTenant($query, $tenantId)`: Explicitly scope ke tenant tertentu
- `scopeWithoutTenantScope($query)`: Bypass tenant scope (untuk superadmin)

## Cara Menggunakan di Routes

### Protected Routes dengan Tenant Scope
```php
Route::middleware(['auth:sanctum', 'tenant.scope'])->group(function () {
    Route::get('/persons', [PersonController::class, 'index']);
    Route::post('/persons', [PersonController::class, 'store']);
});
```

### Superadmin Only Routes (tanpa tenant scope)
```php
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/tenants', [TenantController::class, 'index']); // Superadmin only
});
```

## Request Headers

Untuk non-superadmin, bisa kirim tenant ID via header:
```
X-Tenant-ID: 1
```

Atau via request body:
```json
{
  "tenant_id": 1,
  "name": "John Doe"
}
```

## Contoh Query

### Dengan Tenant Scope (Automatic)
```php
// Otomatis di-filter berdasarkan tenant_id dari middleware
$persons = Person::all(); // Hanya return persons dari tenant aktif
```

### Bypass Tenant Scope (Superadmin)
```php
// Untuk superadmin yang perlu lihat semua data
$allPersons = Person::withoutTenantScope()->get();
```

### Explicit Tenant
```php
// Scope ke tenant tertentu
$persons = Person::forTenant($tenantId)->get();
```


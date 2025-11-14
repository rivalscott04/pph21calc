# Role-Based Access Control (RBAC)

## Overview

Sistem menggunakan middleware `CheckRole` untuk enforce role-based permissions. Setiap endpoint bisa di-protect dengan role tertentu.

## Roles

### Superadmin
- Tidak terikat tenant
- Akses semua endpoint (bypass role check)
- Bisa manage tenants, users, global config

### TENANT_ADMIN
- Terikat pada satu tenant
- Kelola struktur organisasi, pegawai, user-role di tenant
- Kelola branding, modul, konfigurasi pajak lokal
- Lihat laporan umum untuk tenant

### HR
- Terikat pada tenant
- Input & impor penghasilan (earnings) dan potongan
- Menjalankan proses hitung payroll per periode (preview)
- Menghasilkan slip gaji pegawai
- **Tidak bisa** mengubah konfigurasi Modul/Branding

### FINANCE
- Terikat pada tenant
- Review hasil perhitungan PPh 21
- Approval periode payroll (maker-checker)
- Ekspor BPA1/BPA2 dan kirim ke CoreTax
- Lihat log CoreTax & hasil validasi

### VIEWER
- Terikat pada tenant
- Read-only terhadap laporan, payroll, dan beberapa konfigurasi
- Unduh laporan untuk kebutuhan audit

## Middleware: CheckRole

### Penggunaan di Routes

#### Single Role
```php
Route::middleware(['auth:sanctum', 'tenant.scope', 'role:TENANT_ADMIN'])->group(function () {
    Route::post('/settings/branding', [BrandingController::class, 'update']);
});
```

#### Multiple Roles (OR)
```php
Route::middleware(['auth:sanctum', 'tenant.scope', 'role:HR,FINANCE'])->group(function () {
    Route::post('/payroll/{period}/commit', [PayrollController::class, 'commit']);
});
```

#### Superadmin Only (tanpa role check)
```php
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/tenants', [TenantController::class, 'index']); // Superadmin only
});
```

## User Model Methods

### Get Role in Tenant
```php
$role = $user->getRoleInTenant($tenantId);
// Returns: 'TENANT_ADMIN', 'HR', 'FINANCE', 'VIEWER', or null
```

### Check Specific Role
```php
if ($user->hasRoleInTenant($tenantId, 'TENANT_ADMIN')) {
    // User is tenant admin
}
```

### Check Multiple Roles
```php
if ($user->hasAnyRoleInTenant($tenantId, ['HR', 'FINANCE'])) {
    // User is either HR or Finance
}
```

## Error Responses

### 401 UNAUTHENTICATED
User belum login

### 403 TENANT_REQUIRED
User non-superadmin tidak punya tenant context

### 403 TENANT_ACCESS_DENIED
User tidak punya akses ke tenant yang diminta

### 403 INSUFFICIENT_PERMISSIONS
User tidak punya role yang diperlukan untuk akses endpoint

Response:
```json
{
  "message": "You do not have permission to perform this action",
  "error": "INSUFFICIENT_PERMISSIONS",
  "required_roles": ["HR", "FINANCE"],
  "your_role": "VIEWER"
}
```

## Contoh Implementasi di Controller

```php
public function commit(Request $request, $periodId)
{
    // Middleware sudah check role, jadi langsung bisa proceed
    $tenantUser = $request->input('tenant_user'); // Set by CheckRole middleware
    
    // Logic untuk commit payroll
    // ...
}
```

## Role Permissions Matrix

| Action | Superadmin | TENANT_ADMIN | HR | FINANCE | VIEWER |
|--------|-----------|--------------|----|---------|--------|
| Manage Tenants | ✅ | ❌ | ❌ | ❌ | ❌ |
| Manage Users (tenant) | ✅ | ✅ | ❌ | ❌ | ❌ |
| Manage Branding | ✅ | ✅ | ❌ | ❌ | ❌ |
| Manage Modules | ✅ | ✅ | ❌ | ❌ | ❌ |
| Input Payroll | ✅ | ✅ | ✅ | ❌ | ❌ |
| Preview Payroll | ✅ | ✅ | ✅ | ✅ | ❌ |
| Commit Payroll | ✅ | ✅ | ✅ | ✅ | ❌ |
| Approve Payroll | ✅ | ✅ | ❌ | ✅ | ❌ |
| Export CoreTax | ✅ | ✅ | ❌ | ✅ | ❌ |
| View Reports | ✅ | ✅ | ✅ | ✅ | ✅ |


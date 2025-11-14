# Testing Master Data Endpoints

## Setup
1. Pastikan server Laravel sudah running: `php artisan serve`
2. Login dulu untuk mendapatkan token

## 1. Login (Get Token)

### Login as Tenant Admin
```powershell
# PowerShell
$loginResponse = curl.exe -X POST http://localhost:8000/api/auth/login -H "Content-Type: application/json" -H "Accept: application/json" -d '{\"email\":\"tenant_admin@test.local\",\"password\":\"password\"}'
$token = ($loginResponse | ConvertFrom-Json).token
Write-Host "Token: $token"
```

### Login as HR User
```powershell
$loginResponse = curl.exe -X POST http://localhost:8000/api/auth/login -H "Content-Type: application/json" -H "Accept: application/json" -d '{\"email\":\"hr@test.local\",\"password\":\"password\"}'
$token = ($loginResponse | ConvertFrom-Json).token
```

## 2. Test Config Endpoints (Setup dulu)

### Get Modules Config
```powershell
curl.exe -X GET "http://localhost:8000/api/config/modules?tenant_id=1" -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $token"
```

### Get Branding Config
```powershell
curl.exe -X GET "http://localhost:8000/api/config/branding?tenant_id=1" -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $token"
```

### Get Identifier Schemes
```powershell
curl.exe -X GET "http://localhost:8000/api/config/identifier-schemes?tenant_id=1" -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $token"
```

### Create Identifier Scheme (untuk testing)
```powershell
curl.exe -X POST "http://localhost:8000/api/config/identifier-schemes?tenant_id=1" -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $token" -d '{\"code\":\"TEST_EMP_ID\",\"label\":\"Test Employee ID\",\"entity_type\":\"TEST\",\"regex_pattern\":\"^[0-9]{6}$\",\"length_min\":6,\"length_max\":6,\"normalize_rule\":\"NUMERIC\",\"example\":\"123456\",\"checksum_type\":\"NONE\"}'
```

## 3. Test Persons Endpoints

### List Persons
```powershell
curl.exe -X GET "http://localhost:8000/api/persons?tenant_id=1" -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $token"
```

### Create Person
```powershell
curl.exe -X POST "http://localhost:8000/api/persons?tenant_id=1" -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $token" -d '{\"full_name\":\"John Doe\",\"nik\":\"1234567890123456\",\"npwp\":\"123456789012345\",\"birth_date\":\"1990-01-01\"}'
```

**Save person_id dari response untuk testing selanjutnya!**

### Get Person by ID
```powershell
# Replace {person_id} dengan ID dari create response
curl.exe -X GET "http://localhost:8000/api/persons/{person_id}?tenant_id=1" -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $token"
```

### Resolve Person (by identifier/name)
```powershell
curl.exe -X GET "http://localhost:8000/api/persons/resolve?q=John&tenant_id=1" -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $token"
```

### Add Identifier to Person
```powershell
# Replace {person_id} dan {scheme_id} dengan ID yang sesuai
curl.exe -X POST "http://localhost:8000/api/persons/{person_id}/identifiers?tenant_id=1" -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $token" -d '{\"scheme_id\":1,\"raw_value\":\"123456\",\"is_primary\":true}'
```

## 4. Test Org Units Endpoints

### List Org Units
```powershell
curl.exe -X GET "http://localhost:8000/api/org-units?tenant_id=1" -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $token"
```

### Get Org Units Tree
```powershell
curl.exe -X GET "http://localhost:8000/api/org-units/tree?tenant_id=1" -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $token"
```

### Create Org Unit (Root)
```powershell
curl.exe -X POST "http://localhost:8000/api/org-units?tenant_id=1" -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $token" -d '{\"code\":\"HQ\",\"name\":\"Headquarters\",\"type\":\"HQ\"}'
```

**Save org_unit_id dari response!**

### Create Child Org Unit
```powershell
# Replace {parent_id} dengan ID dari create sebelumnya
curl.exe -X POST "http://localhost:8000/api/org-units?tenant_id=1" -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $token" -d '{\"code\":\"BR001\",\"name\":\"Branch 001\",\"type\":\"BRANCH\",\"parent_id\":{parent_id}}'
```

### Get Org Unit by ID
```powershell
# Replace {org_unit_id} dengan ID yang sesuai
curl.exe -X GET "http://localhost:8000/api/org-units/{org_unit_id}?tenant_id=1" -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $token"
```

## 5. Test Employments Endpoints

### List Employments
```powershell
curl.exe -X GET "http://localhost:8000/api/employments?tenant_id=1" -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $token"
```

### Create Employment
```powershell
# Replace {person_id} dan {org_unit_id} dengan ID yang sesuai
curl.exe -X POST "http://localhost:8000/api/employments?tenant_id=1" -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $token" -d '{\"person_id\":\"{person_id}\",\"org_unit_id\":{org_unit_id},\"employment_type\":\"tetap\",\"start_date\":\"2024-01-01\",\"primary_payroll\":true}'
```

**Save employment_id dari response!**

### Get Employment by ID
```powershell
# Replace {employment_id} dengan ID yang sesuai
curl.exe -X GET "http://localhost:8000/api/employments/{employment_id}?tenant_id=1" -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $token"
```

## 6. Test Payroll Subjects Endpoints

### List Payroll Subjects
```powershell
curl.exe -X GET "http://localhost:8000/api/payroll-subjects?tenant_id=1" -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $token"
```

### Create Payroll Subject
```powershell
# Replace {employment_id} dengan ID yang sesuai
curl.exe -X POST "http://localhost:8000/api/payroll-subjects?tenant_id=1" -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $token" -d '{\"employment_id\":{employment_id},\"ptkp_code\":\"TK0\",\"has_npwp\":true,\"active\":true}'
```

**Save payroll_subject_id dari response!**

### Get Payroll Subject by ID
```powershell
# Replace {payroll_subject_id} dengan ID yang sesuai
curl.exe -X GET "http://localhost:8000/api/payroll-subjects/{payroll_subject_id}?tenant_id=1" -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $token"
```

## 7. Test Eager Loading (Check N+1)

### Test dengan query logging
```powershell
# Enable query logging di Laravel (temporary)
# Atau check response time - harus cepat tanpa N+1

# Test Person dengan identifiers
curl.exe -X GET "http://localhost:8000/api/persons?tenant_id=1&per_page=10" -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $token"

# Test Employment dengan person dan org unit
curl.exe -X GET "http://localhost:8000/api/employments?tenant_id=1&per_page=10" -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $token"

# Test Org Units Tree (harus cepat meski banyak level)
curl.exe -X GET "http://localhost:8000/api/org-units/tree?tenant_id=1" -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $token"
```

## Notes
- Semua endpoints memerlukan `tenant_id` sebagai query parameter (kecuali superadmin)
- Token harus di-set di variable `$token` sebelum testing
- Replace semua placeholder `{id}` dengan ID yang sesuai dari response sebelumnya
- Check response untuk memastikan eager loading bekerja (tidak ada null relationships)


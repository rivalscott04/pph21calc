<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CalculatorController;
use App\Http\Controllers\Api\ComponentController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\CoreTaxController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EmploymentController;
use App\Http\Controllers\Api\OrgUnitController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\PeriodController;
use App\Http\Controllers\Api\PersonController;
use App\Http\Controllers\Api\TenantController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/auth/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Superadmin routes (no tenant scope needed)
    Route::prefix('tenants')->group(function () {
        Route::get('/', [TenantController::class, 'index']);
        Route::post('/', [TenantController::class, 'store']);
        Route::get('/{id}', [TenantController::class, 'show']);
        Route::patch('/{id}', [TenantController::class, 'update']);
        Route::get('/{id}/users', [TenantController::class, 'users']);
        Route::post('/{id}/users', [TenantController::class, 'createUser']);
    });

    // Config routes (with tenant scope)
    Route::middleware(['tenant.scope'])->prefix('config')->group(function () {
        // Modules
        Route::get('/modules', [ConfigController::class, 'getModules']);
        Route::patch('/modules', [ConfigController::class, 'updateModules']);

        // Branding
        Route::get('/branding', [ConfigController::class, 'getBranding']);
        Route::patch('/branding', [ConfigController::class, 'updateBranding']);

        // Identifier Schemes
        Route::get('/identifier-schemes', [ConfigController::class, 'getIdentifierSchemes']);
        Route::post('/identifier-schemes', [ConfigController::class, 'createIdentifierScheme']);
        Route::patch('/identifier-schemes/{id}', [ConfigController::class, 'updateIdentifierScheme']);
    });

    // Identifier check (with tenant scope)
    Route::middleware(['tenant.scope'])->get('/identifiers/check-unique', [ConfigController::class, 'checkIdentifierUnique']);

    // Master Data routes (with tenant scope)
    Route::middleware(['tenant.scope'])->group(function () {
        // Persons
        Route::get('/persons', [PersonController::class, 'index']);
        Route::post('/persons', [PersonController::class, 'store']);
        Route::get('/persons/resolve', [PersonController::class, 'resolve']);
        Route::get('/persons/{id}', [PersonController::class, 'show']);
        Route::post('/persons/{id}/identifiers', [PersonController::class, 'addIdentifier']);

        // Org Units
        Route::get('/org-units', [OrgUnitController::class, 'index']);
        Route::get('/org-units/tree', [OrgUnitController::class, 'tree']);
        Route::post('/org-units', [OrgUnitController::class, 'store']);
        Route::get('/org-units/{id}', [OrgUnitController::class, 'show']);
        Route::patch('/org-units/{id}', [OrgUnitController::class, 'update']);

        // Components
        Route::get('/components', [ComponentController::class, 'index']);
        Route::post('/components', [ComponentController::class, 'store']);
        Route::get('/components/{id}', [ComponentController::class, 'show']);
        Route::patch('/components/{id}', [ComponentController::class, 'update']);

        // Employments
        Route::get('/employments', [EmploymentController::class, 'index']);
        Route::post('/employments', [EmploymentController::class, 'store']);
        Route::get('/employments/{id}', [EmploymentController::class, 'show']);
        Route::patch('/employments/{id}', [EmploymentController::class, 'update']);

        // Payroll Subjects
        Route::get('/payroll-subjects', [EmploymentController::class, 'payrollSubjects']);
        Route::post('/payroll-subjects', [EmploymentController::class, 'createPayrollSubject']);
        Route::get('/payroll-subjects/{id}', [EmploymentController::class, 'showPayrollSubject']);
        Route::patch('/payroll-subjects/{id}', [EmploymentController::class, 'updatePayrollSubject']);

        // Periods
        Route::get('/periods', [PeriodController::class, 'index']);
        Route::post('/periods', [PeriodController::class, 'store']);
        Route::get('/periods/{id}', [PeriodController::class, 'show']);
        Route::patch('/periods/{id}/status', [PeriodController::class, 'updateStatus']);

        // Earnings
        Route::get('/earnings', [PayrollController::class, 'earnings']);
        Route::post('/earnings', [PayrollController::class, 'storeEarnings']);

        // Deductions
        Route::get('/deductions', [PayrollController::class, 'deductions']);
        Route::post('/deductions', [PayrollController::class, 'storeDeductions']);

        // Payroll Calculations
        Route::post('/payroll/{period}/preview', [PayrollController::class, 'preview']);
        Route::post('/payroll/{period}/commit', [PayrollController::class, 'commit']);
        Route::get('/payroll/{period}/summary', [PayrollController::class, 'summary']);
        Route::get('/payroll/{period}/slip/{employment}', [PayrollController::class, 'slip']);

        // Calculator (standalone)
        Route::post('/calculator/pph21', [CalculatorController::class, 'calculatePph21']);
        Route::post('/calculator/batch', [CalculatorController::class, 'calculateBatch']);
        Route::get('/calculator/employees', [CalculatorController::class, 'searchEmployees']);
        Route::post('/calculator/history', [CalculatorController::class, 'saveHistory']);
        Route::get('/calculator/history', [CalculatorController::class, 'getHistory']);
        Route::get('/calculator/history/summary', [CalculatorController::class, 'getHistorySummary']);
        Route::get('/calculator/history/employees', [CalculatorController::class, 'getEmployeeHistoryList']);
        Route::get('/calculator/history/{employmentId}', [CalculatorController::class, 'getEmployeeHistoryDetail']);

        // CoreTax Integration (with tenant scope)
        Route::post('/coretax/export', [CoreTaxController::class, 'export']);
        Route::post('/coretax/upload', [CoreTaxController::class, 'upload']);
        Route::get('/coretax/logs', [CoreTaxController::class, 'logs']);
        Route::get('/coretax/logs/{id}', [CoreTaxController::class, 'showLog']);

        // Activity Logs (with tenant scope)
        Route::get('/logs/activity', [ActivityLogController::class, 'index']);
        Route::get('/logs/activity/{id}', [ActivityLogController::class, 'show']);

        // Dashboard (with tenant scope)
        Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
        Route::get('/dashboard/chart', [DashboardController::class, 'chart']);
    });
});


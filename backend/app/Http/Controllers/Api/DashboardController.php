<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Employment;
use App\Models\Period;
use App\Models\Person;
use App\Models\PayrollCalculation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get dashboard summary
     */
    public function summary(Request $request)
    {
        $tenantId = $request->input('tenant_id') 
            ?? (app()->bound('tenant_id') ? app('tenant_id') : null);

        // Total employees (active employments)
        $totalEmployees = Employment::where('tenant_id', $tenantId)
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            })
            ->count();

        // Total persons
        $totalPersons = Person::where('tenant_id', $tenantId)->count();

        // Total periods
        $totalPeriods = Period::where('tenant_id', $tenantId)->count();

        // Current year periods
        $currentYear = date('Y');
        $currentYearPeriods = Period::where('tenant_id', $tenantId)
            ->where('year', $currentYear)
            ->count();

        // Total PPh21 YTD (current year) - Optimized with join to avoid N+1
        $totalPph21Ytd = DB::table('payroll_calculations')
            ->join('periods', 'payroll_calculations.period_id', '=', 'periods.id')
            ->where('payroll_calculations.tenant_id', $tenantId)
            ->where('periods.tenant_id', $tenantId)
            ->where('periods.year', $currentYear)
            ->sum('payroll_calculations.pph21_masa');

        // Total PPh21 this month - Optimized with join to avoid N+1
        $currentMonth = date('n');
        $totalPph21ThisMonth = DB::table('payroll_calculations')
            ->join('periods', 'payroll_calculations.period_id', '=', 'periods.id')
            ->where('payroll_calculations.tenant_id', $tenantId)
            ->where('periods.tenant_id', $tenantId)
            ->where('periods.year', $currentYear)
            ->where('periods.month', $currentMonth)
            ->sum('payroll_calculations.pph21_masa');

        // Recent activity count (last 7 days)
        $recentActivityCount = ActivityLog::where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        // Pending periods (draft/reviewed)
        $pendingPeriods = Period::where('tenant_id', $tenantId)
            ->whereIn('status', ['draft', 'reviewed'])
            ->count();

        return response()->json([
            'total_employees' => $totalEmployees,
            'total_persons' => $totalPersons,
            'total_periods' => $totalPeriods,
            'current_year_periods' => $currentYearPeriods,
            'total_pph21_ytd' => (float) $totalPph21Ytd,
            'total_pph21_this_month' => (float) $totalPph21ThisMonth,
            'recent_activity_count' => $recentActivityCount,
            'pending_periods' => $pendingPeriods,
        ]);
    }

    /**
     * Get chart data
     */
    public function chart(Request $request)
    {
        $tenantId = $request->input('tenant_id') 
            ?? (app()->bound('tenant_id') ? app('tenant_id') : null);

        $type = $request->get('type', 'pph21_monthly'); // pph21_monthly, employees_by_org, activity_timeline

        switch ($type) {
            case 'pph21_monthly':
                return $this->getPph21MonthlyChart($tenantId);
            
            case 'employees_by_org':
                return $this->getEmployeesByOrgChart($tenantId);
            
            case 'activity_timeline':
                return $this->getActivityTimelineChart($tenantId);
            
            default:
                return response()->json([
                    'message' => 'Invalid chart type',
                ], 422);
        }
    }

    /**
     * Get PPh21 monthly chart data (last 12 months)
     */
    private function getPph21MonthlyChart($tenantId)
    {
        $currentYear = date('Y');
        $currentMonth = date('n');

        $data = DB::table('payroll_calculations')
            ->join('periods', 'payroll_calculations.period_id', '=', 'periods.id')
            ->where('payroll_calculations.tenant_id', $tenantId)
            ->where('periods.tenant_id', $tenantId)
            ->where('periods.year', $currentYear)
            ->select(
                DB::raw('periods.month as month'),
                DB::raw('SUM(payroll_calculations.pph21_masa) as total_pph21'),
                DB::raw('COUNT(DISTINCT payroll_calculations.employment_id) as employee_count')
            )
            ->groupBy('periods.month')
            ->orderBy('periods.month')
            ->get();

        // Fill missing months with 0
        $chartData = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthData = $data->firstWhere('month', $month);
            $chartData[] = [
                'month' => $month,
                'month_name' => date('M', mktime(0, 0, 0, $month, 1)),
                'total_pph21' => $monthData ? (float) $monthData->total_pph21 : 0,
                'employee_count' => $monthData ? (int) $monthData->employee_count : 0,
            ];
        }

        return response()->json([
            'type' => 'pph21_monthly',
            'data' => $chartData,
        ]);
    }

    /**
     * Get employees by organization unit chart
     */
    private function getEmployeesByOrgChart($tenantId)
    {
        $data = DB::table('employments')
            ->join('org_units', 'employments.org_unit_id', '=', 'org_units.id')
            ->where('employments.tenant_id', $tenantId)
            ->where('org_units.tenant_id', $tenantId)
            ->where(function ($query) {
                $query->whereNull('employments.end_date')
                    ->orWhere('employments.end_date', '>=', now());
            })
            ->select(
                'org_units.name as org_unit_name',
                DB::raw('COUNT(employments.id) as employee_count')
            )
            ->groupBy('org_units.id', 'org_units.name')
            ->orderBy('employee_count', 'desc')
            ->limit(10) // Top 10 org units
            ->get();

        $chartData = $data->map(function ($item) {
            return [
                'org_unit' => $item->org_unit_name,
                'employee_count' => (int) $item->employee_count,
            ];
        });

        return response()->json([
            'type' => 'employees_by_org',
            'data' => $chartData,
        ]);
    }

    /**
     * Get activity timeline chart (last 30 days)
     */
    private function getActivityTimelineChart($tenantId)
    {
        $data = ActivityLog::where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subDays(30))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as activity_count'),
                DB::raw('COUNT(DISTINCT user_id) as unique_users')
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        $chartData = $data->map(function ($item) {
            return [
                'date' => $item->date,
                'activity_count' => (int) $item->activity_count,
                'unique_users' => (int) $item->unique_users,
            ];
        });

        return response()->json([
            'type' => 'activity_timeline',
            'data' => $chartData,
        ]);
    }
}

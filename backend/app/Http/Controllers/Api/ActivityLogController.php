<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Get activity logs
     */
    public function index(Request $request)
    {
        $query = ActivityLog::with(['user', 'tenant'])
            ->orderBy('created_at', 'desc');

        // Filter by table if provided
        if ($request->has('table_name')) {
            $query->where('table_name', $request->table_name);
        }

        // Filter by action if provided
        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        // Filter by date range if provided
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate($request->get('per_page', 15));

        return response()->json($logs);
    }

    /**
     * Get specific activity log
     */
    public function show($id)
    {
        $log = ActivityLog::with(['user', 'tenant'])->findOrFail($id);

        return response()->json($log);
    }
}

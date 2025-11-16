<?php

namespace App\Traits;

use App\Models\ActivityLog;
use App\Models\ConfigModule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait LogsActivity
{
    /**
     * Boot the trait
     */
    protected static function bootLogsActivity()
    {
        // Log on creating (insert)
        static::creating(function (Model $model) {
            static::logActivity($model, 'insert', null, $model->getAttributes());
        });

        // Log on updating
        static::updating(function (Model $model) {
            static::logActivity($model, 'update', $model->getOriginal(), $model->getAttributes());
        });

        // Log on deleting
        static::deleting(function (Model $model) {
            static::logActivity($model, 'delete', $model->getAttributes(), null);
        });
    }

    /**
     * Log activity to activity_logs table
     */
    protected static function logActivity(Model $model, string $action, ?array $before, ?array $after)
    {
        // Skip if audit_trail is disabled for this tenant
        if (!static::shouldLogActivity($model)) {
            return;
        }

        // Skip logging for activity_logs table itself
        if ($model->getTable() === 'activity_logs') {
            return;
        }

        // Get tenant_id from model
        $tenantId = null;
        if (property_exists($model, 'tenant_id') || $model->getAttribute('tenant_id')) {
            $tenantId = $model->getAttribute('tenant_id');
        } elseif (method_exists($model, 'tenant_id')) {
            $tenantId = $model->tenant_id;
        }

        // Get user_id from auth
        $userId = Auth::id();

        // Filter sensitive fields (password, tokens, etc.)
        $beforeFiltered = $before ? static::filterSensitiveFields($before) : null;
        $afterFiltered = $after ? static::filterSensitiveFields($after) : null;

        // Only log if there are actual changes (for updates)
        if ($action === 'update' && $beforeFiltered === $afterFiltered) {
            return;
        }

        ActivityLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'table_name' => $model->getTable(),
            'before' => $beforeFiltered,
            'after' => $afterFiltered,
            'action' => $action,
        ]);
    }

    /**
     * Check if activity logging should be enabled for this tenant
     */
    protected static function shouldLogActivity(Model $model): bool
    {
        // Get tenant_id
        $tenantId = null;
        if (property_exists($model, 'tenant_id') || $model->getAttribute('tenant_id')) {
            $tenantId = $model->getAttribute('tenant_id');
        }

        // If no tenant_id, allow logging (for superadmin actions)
        if (!$tenantId) {
            return true;
        }

        // Check if audit_trail module is enabled for this tenant
        $config = ConfigModule::where('tenant_id', $tenantId)->first();
        if (!$config) {
            return true; // Default to enabled if config doesn't exist
        }

        return $config->audit_trail ?? true;
    }

    /**
     * Filter sensitive fields from logging
     */
    protected static function filterSensitiveFields(array $data): array
    {
        $sensitiveFields = [
            'password',
            'remember_token',
            'api_token',
            'token',
            'secret',
            'key',
            'access_token',
            'refresh_token',
        ];

        $filtered = [];
        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), $sensitiveFields)) {
                $filtered[$key] = '***REDACTED***';
            } else {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }
}


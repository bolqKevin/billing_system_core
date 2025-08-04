<?php

namespace App\Helpers;

use App\Models\UserMovementLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuditHelper
{
    /**
     * Log user action
     */
    public static function logAction($action, $recordId = null, $details = null)
    {
        try {
            UserMovementLog::create([
                'user_id' => Auth::id(),
                'action_performed' => $action,
                'affected_record_id' => $recordId,
            ]);
        } catch (\Exception $e) {
            // Log error but don't break the main flow
            Log::error('Error logging user action: ' . $e->getMessage());
        }
    }

    /**
     * Log creation
     */
    public static function logCreate($recordId = null)
    {
        self::logAction('Create', $recordId);
    }

    /**
     * Log update
     */
    public static function logUpdate($recordId = null)
    {
        self::logAction('Update', $recordId);
    }

    /**
     * Log delete
     */
    public static function logDelete($recordId = null)
    {
        self::logAction('Delete', $recordId);
    }

    /**
     * Log view
     */
    public static function logView($recordId = null)
    {
        self::logAction('View', $recordId);
    }
} 
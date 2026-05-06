<?php

use App\Models\Log;

/**
 * Add a log entry to the database
 * 
 * @param string $action - e.g. "backup_failed", "server_down", "alert_created"
 * @param string $type - server, backup, nvr, alert
 * @param string|null $message - optional message
 * 
 * @return Log|null
 */
function add_log($action, $type, $message = null)
{
    try {
        return Log::create([
            'action' => $action,
            'type' => $type,
            'message' => $message
        ]);
    } catch (\Exception $e) {
        // Silently fail to avoid breaking monitoring logic
        \Log::error('Failed to create log entry', [
            'action' => $action,
            'type' => $type,
            'message' => $message,
            'error' => $e->getMessage()
        ]);
        return null;
    }
}

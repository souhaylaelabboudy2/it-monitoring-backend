<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Backup extends Model
{
    public $timestamps = true;
    
    protected $fillable = [
        'server_name',
        'status',
        'consecutive_failures',
        'size_gb',
        'duration_minutes',
        'error_message'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'date' => 'datetime'
    ];

    /**
     * Get the last backup for a server
     */
    public static function lastForServer(string $serverName): ?self
    {
        return self::where('server_name', $serverName)
            ->latest('created_at')
            ->first();
    }

    /**
     * Get failed backups for a server
     */
    public static function failedForServer(string $serverName)
    {
        return self::where('server_name', $serverName)
            ->where('status', 'failed')
            ->latest('created_at')
            ->get();
    }

    /**
     * Get consecutive failure count for a server
     */
    public static function getConsecutiveFailures(string $serverName): int
    {
        $last = self::lastForServer($serverName);
        return $last ? $last->consecutive_failures : 0;
    }

    /**
     * Check if backup should trigger alert
     */
    public function shouldAlert(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Get alert severity based on consecutive failures
     */
    public function getAlertSeverity(): string
    {
        if ($this->consecutive_failures >= 3) {
            return 'critical';
        } elseif ($this->consecutive_failures >= 1) {
            return 'warning';
        }
        return 'info';
    }

    /**
     * Generate alert title
     */
    public function getAlertTitle(): string
    {
        if ($this->consecutive_failures >= 3) {
            return "🔴 CRITICAL: Backup failed {$this->consecutive_failures}x for {$this->server_name}";
        } elseif ($this->consecutive_failures >= 1) {
            return "⚠️  WARNING: Backup failed for {$this->server_name}";
        }
        return "ℹ️  Backup Info: {$this->server_name}";
    }

    /**
     * Generate alert message
     */
    public function getAlertMessage(): string
    {
        $msg = "Server: {$this->server_name}\n";
        $msg .= "Status: {$this->status}\n";
        $msg .= "Timestamp: " . $this->created_at->format('Y-m-d H:i:s') . "\n";
        $msg .= "Consecutive failures: {$this->consecutive_failures}\n";
        
        if ($this->duration_minutes) {
            $msg .= "Duration: {$this->duration_minutes} minutes\n";
        }
        
        if ($this->size_gb) {
            $msg .= "Size: {$this->size_gb} GB\n";
        }
        
        if ($this->error_message) {
            $msg .= "Error: {$this->error_message}\n";
        }
        
        return trim($msg);
    }
}
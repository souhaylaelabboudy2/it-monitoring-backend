<?php

namespace App\Http\Controllers;

use App\Models\AlertSystem;
use App\Models\Incident;
use App\Models\Backup;
use App\Models\Nvr;
use App\Models\Server;
use App\Models\Log;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * RSSI Reporting Summary
     * GET /api/reports/rssi-summary
     * 
     * Query parameters:
     * ?period=today|7days|30days
     */
    public function rssiSummary(Request $request)
    {
        try {
            // Get period from request (default: today)
            $period = $request->query('period', 'today');
            $dateRange = $this->getDateRange($period);

            // Get summary counts
            $summary = [
                'critical_alerts' => AlertSystem::where('severity', 'critical')
                    ->whereBetween('last_seen', $dateRange)
                    ->count(),
                
                'warning_alerts' => AlertSystem::where('severity', 'warning')
                    ->whereBetween('last_seen', $dateRange)
                    ->count(),
                
                'info_alerts' => AlertSystem::where('severity', 'info')
                    ->whereBetween('last_seen', $dateRange)
                    ->count(),
                
                'open_incidents' => Incident::where('status', 'open')
                    ->whereBetween('created_at', $dateRange)
                    ->count(),
                
                'resolved_incidents' => Incident::where('status', 'resolved')
                    ->whereBetween('updated_at', $dateRange)
                    ->count(),
                
                'backup_failures' => Backup::where('status', 'failed')
                    ->whereBetween('created_at', $dateRange)
                    ->count(),
                
                'backup_success' => Backup::where('status', 'success')
                    ->whereBetween('created_at', $dateRange)
                    ->count(),
                
                'nvr_issues' => Nvr::where('status', 'offline')
                    ->count(),
                
                'offline_servers' => Server::where('status', 'offline')
                    ->count(),
                
                'total_logs' => Log::whereBetween('created_at', $dateRange)
                    ->count(),
            ];

            // Get detailed data
            $details = [
                'alerts' => AlertSystem::whereBetween('last_seen', $dateRange)
                    ->orderBy('last_seen', 'desc')
                    ->limit(50)
                    ->get(),
                
                'incidents' => Incident::whereBetween('created_at', $dateRange)
                    ->orderBy('created_at', 'desc')
                    ->limit(50)
                    ->get(),
                
                'backups' => Backup::whereBetween('created_at', $dateRange)
                    ->orderBy('created_at', 'desc')
                    ->limit(50)
                    ->get(),
                
                'nvrs' => Nvr::orderBy('id', 'desc')
                    ->get(),
                
                'logs' => Log::whereBetween('created_at', $dateRange)
                    ->orderBy('created_at', 'desc')
                    ->limit(100)
                    ->get(),
            ];

            return response()->json([
                'success' => true,
                'period' => $period,
                'date_range' => [
                    'start' => $dateRange[0]->toIso8601String(),
                    'end' => $dateRange[1]->toIso8601String()
                ],
                'summary' => $summary,
                'details' => $details,
                'timestamp' => now()->toIso8601String()
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate RSSI report',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get date range based on period
     */
    private function getDateRange(string $period): array
    {
        $end = Carbon::now();
        
        $start = match($period) {
            'today' => $end->clone()->startOfDay(),
            '7days' => $end->clone()->subDays(7)->startOfDay(),
            '30days' => $end->clone()->subDays(30)->startOfDay(),
            default => $end->clone()->startOfDay()
        };

        return [$start, $end];
    }

    /**
     * Alert Summary Report
     * GET /api/reports/alerts
     */
    public function alertsSummary(Request $request)
    {
        try {
            $period = $request->query('period', 'today');
            $dateRange = $this->getDateRange($period);

            $alerts = AlertSystem::whereBetween('last_seen', $dateRange)
                ->orderBy('last_seen', 'desc')
                ->get()
                ->groupBy('severity');

            return response()->json([
                'success' => true,
                'period' => $period,
                'critical' => $alerts->get('critical', []),
                'warning' => $alerts->get('warning', []),
                'info' => $alerts->get('info', []),
                'total' => AlertSystem::whereBetween('last_seen', $dateRange)->count()
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve alerts report',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Incidents Report
     * GET /api/reports/incidents
     */
    public function incidentsSummary(Request $request)
    {
        try {
            $period = $request->query('period', 'today');
            $dateRange = $this->getDateRange($period);

            $incidents = Incident::whereBetween('created_at', $dateRange)
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy('status');

            $severities = Incident::whereBetween('created_at', $dateRange)
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy('severity');

            return response()->json([
                'success' => true,
                'period' => $period,
                'by_status' => [
                    'open' => $incidents->get('open', []),
                    'acknowledged' => $incidents->get('acknowledged', []),
                    'resolved' => $incidents->get('resolved', [])
                ],
                'by_severity' => [
                    'critical' => $severities->get('critical', []),
                    'warning' => $severities->get('warning', []),
                    'info' => $severities->get('info', [])
                ],
                'total' => Incident::whereBetween('created_at', $dateRange)->count()
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve incidents report',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Backup Report
     * GET /api/reports/backups
     */
    public function backupsSummary(Request $request)
    {
        try {
            $period = $request->query('period', 'today');
            $dateRange = $this->getDateRange($period);

            $backups = Backup::whereBetween('created_at', $dateRange)
                ->orderBy('created_at', 'desc')
                ->get();

            $byStatus = $backups->groupBy('status');

            return response()->json([
                'success' => true,
                'period' => $period,
                'success_count' => $byStatus->get('success', [])->count(),
                'failed_count' => $byStatus->get('failed', [])->count(),
                'success_rate' => $this->calculateSuccessRate($byStatus),
                'backups' => $backups,
                'total' => $backups->count()
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve backups report',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * NVR Report
     * GET /api/reports/nvrs
     */
    public function nvrsSummary(Request $request)
    {
        try {
            $nvrs = Nvr::orderBy('updated_at', 'desc')
                ->get();

            $byStatus = $nvrs->groupBy('status');

            return response()->json([
                'success' => true,
                'online_count' => $byStatus->get('online', [])->count(),
                'offline_count' => $byStatus->get('offline', [])->count(),
                'nvrs' => $nvrs,
                'total' => $nvrs->count()
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve NVRs report',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Server Report
     * GET /api/reports/servers
     */
    public function serversSummary(Request $request)
    {
        try {
            $servers = Server::orderBy('id', 'desc')
                ->get();

            $byStatus = $servers->groupBy('status');

            return response()->json([
                'success' => true,
                'online_count' => $byStatus->get('online', [])->count(),
                'offline_count' => $byStatus->get('offline', [])->count(),
                'servers' => $servers,
                'total' => $servers->count()
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve servers report',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Calculate success rate for backups
     */
    private function calculateSuccessRate(array $byStatus): string
    {
        $success = count($byStatus['success'] ?? []);
        $failed = count($byStatus['failed'] ?? []);
        $total = $success + $failed;

        if ($total === 0) {
            return '0%';
        }

        $rate = ($success / $total) * 100;
        return round($rate, 2) . '%';
    }

    /**
     * Health Check Report
     * GET /api/reports/health
     */
    public function healthCheck(Request $request)
    {
        try {
            $period = $request->query('period', 'today');
            $dateRange = $this->getDateRange($period);

            $criticalAlerts = AlertSystem::where('severity', 'critical')
                ->whereBetween('last_seen', $dateRange)
                ->count();

            $openIncidents = Incident::where('status', 'open')
                ->whereBetween('created_at', $dateRange)
                ->count();

            $offlineServers = Server::where('status', 'offline')->count();

            $offlineNvrs = Nvr::where('status', 'offline')->count();

            $backupFailures = Backup::where('status', 'failed')
                ->whereBetween('created_at', $dateRange)
                ->count();

            // Determine health status
            $health = 'good';
            $issues = [];

            if ($criticalAlerts > 0) {
                $health = 'critical';
                $issues[] = "$criticalAlerts critical alerts";
            } elseif ($openIncidents > 0) {
                $health = 'warning';
                $issues[] = "$openIncidents open incidents";
            }

            if ($offlineServers > 0) {
                $issues[] = "$offlineServers offline servers";
            }

            if ($offlineNvrs > 0) {
                $issues[] = "$offlineNvrs offline NVRs";
            }

            if ($backupFailures > 0) {
                $issues[] = "$backupFailures backup failures";
            }

            return response()->json([
                'success' => true,
                'period' => $period,
                'health' => $health,
                'issues' => $issues,
                'metrics' => [
                    'critical_alerts' => $criticalAlerts,
                    'open_incidents' => $openIncidents,
                    'offline_servers' => $offlineServers,
                    'offline_nvrs' => $offlineNvrs,
                    'backup_failures' => $backupFailures
                ]
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve health report',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

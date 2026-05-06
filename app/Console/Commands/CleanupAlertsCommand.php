<?php

namespace App\Console\Commands;

use App\Models\AlertSystem;
use Illuminate\Console\Command;

class CleanupAlertsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alerts:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete resolved alerts older than 30 days to prevent database bloat';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $thirtyDaysAgo = now()->subDays(30);

            $deletedCount = AlertSystem::where('status', 'resolved')
                ->where('created_at', '<', $thirtyDaysAgo)
                ->delete();

            $this->info("✅ Cleanup completed: {$deletedCount} resolved alerts deleted (older than 30 days)");

            // Log the cleanup action
            \Log::info("Alert cleanup executed", [
                'deleted_count' => $deletedCount,
                'cutoff_date' => $thirtyDaysAgo->toDateTimeString()
            ]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Cleanup failed: " . $e->getMessage());
            \Log::error("Alert cleanup failed", ['error' => $e->getMessage()]);

            return self::FAILURE;
        }
    }
}

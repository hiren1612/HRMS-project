<?php

namespace App\Console\Commands;

use App\Models\Monitoring\MonitoringAuditLog;
use App\Models\Monitoring\Screenshot;
use App\Services\Monitoring\TrackingSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Deletes monitoring data that has outlived its retention period.
 *
 * Without this the retention setting is a promise nobody keeps: the number
 * sits in the settings form and the images stay on disk forever. Of
 * everything in the module this is the part most likely to be quietly
 * skipped, and the part whose absence would matter most if anyone ever
 * asked how long screenshots are kept.
 *
 * Files and rows go together. A row with no file is a broken thumbnail; a
 * file with no row is invisible and never cleaned up.
 */
class PruneMonitoringData extends Command
{
    protected $signature = 'monitoring:prune
                            {--dry-run : List what would be deleted without deleting it}
                            {--chunk=200 : Rows to process at a time}';

    protected $description = 'Delete screenshots and audit entries past their retention period';

    public function handle(TrackingSettings $settings): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(1, (int) $this->option('chunk'));

        $days = $settings->int('screenshots.retention_days', 30);

        if ($days < 1) {
            $this->error('screenshots.retention_days is not a sensible number of days; nothing was deleted.');

            return self::FAILURE;
        }

        $cutoff = Carbon::now()->subDays($days)->startOfDay();

        $this->info($dryRun ? 'Dry run — nothing will be deleted.' : 'Pruning monitoring data.');
        $this->line("Screenshots older than {$days} days (before {$cutoff->toDateString()}).");

        $deleted = 0;
        $missingFiles = 0;
        $bytes = 0;

        // withTrashed: a soft-deleted row still has its file on disk, and
        // that file is the thing retention is actually about.
        Screenshot::withTrashed()
            ->olderThan($cutoff)
            ->chunkById($chunk, function ($screenshots) use ($dryRun, &$deleted, &$missingFiles, &$bytes) {
                foreach ($screenshots as $screenshot) {
                    $bytes += (int) $screenshot->bytes;

                    if (! $screenshot->fileExists()) {
                        $missingFiles++;
                    }

                    if ($dryRun) {
                        $deleted++;
                        continue;
                    }

                    // File first. A failure here leaves the row, so the next
                    // run tries again; the other order would orphan the file
                    // where nothing would ever find it.
                    $screenshot->deleteFiles();
                    $screenshot->forceDelete();

                    $deleted++;
                }
            });

        $this->line(sprintf(
            '%s %d screenshot%s (%s)%s',
            $dryRun ? 'Would delete' : 'Deleted',
            $deleted,
            $deleted === 1 ? '' : 's',
            $this->humanBytes($bytes),
            $missingFiles > 0 ? ", {$missingFiles} already had no file on disk" : '',
        ));

        // The audit log is kept far longer than the images. It is the record
        // of who looked at what, which is exactly what you want to still
        // have when somebody asks months later.
        $auditDays = (int) config('monitoring.audit.retention_days', 365);
        $auditCutoff = Carbon::now()->subDays($auditDays);

        $auditCount = MonitoringAuditLog::where('created_at', '<', $auditCutoff)->count();

        if ($auditCount > 0) {
            if (! $dryRun) {
                MonitoringAuditLog::where('created_at', '<', $auditCutoff)->delete();
            }

            $this->line(sprintf(
                '%s %d audit %s older than %d days.',
                $dryRun ? 'Would delete' : 'Deleted',
                $auditCount,
                $auditCount === 1 ? 'entry' : 'entries',
                $auditDays,
            ));
        }

        if (! $dryRun && $deleted > 0) {
            MonitoringAuditLog::record(
                MonitoringAuditLog::SCREENSHOT_DELETED,
                null,
                null,
                ['count' => $deleted, 'retention_days' => $days, 'automatic' => true],
            );
        }

        return self::SUCCESS;
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return "{$bytes} B";
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024) . ' KB';
        }

        return round($bytes / 1024 / 1024, 1) . ' MB';
    }
}

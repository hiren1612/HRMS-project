<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use Illuminate\Console\Command;

/**
 * QA H1: close sessions nobody clocked out of.
 *
 * Without this, a forgotten clock-out runs forever — the live data had a
 * session open since 17 June that reached 2,879 hours. Runs on the schedule
 * just after midnight, and can be run by hand to tidy historic rows.
 */
class CloseStaleAttendance extends Command
{
    protected $signature = 'attendance:close-stale
                            {--dry-run : List what would change without writing}';

    protected $description = 'Close attendance sessions left open past a plausible working day';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $stale = Attendance::with('employee')
            ->whereNull('clock_out')
            ->get()
            ->filter(fn (Attendance $a) => $a->clock_in->diffInMinutes(now()) > Attendance::MAX_SESSION_MINUTES);

        if ($stale->isEmpty()) {
            $this->info('No stale sessions.');

            return self::SUCCESS;
        }

        foreach ($stale as $session) {
            // Close at a plausible end, not "now" — otherwise a session left
            // open for months would still bank months of time.
            $closeAt = $session->clock_in->copy()->addMinutes(Attendance::MAX_SESSION_MINUTES);

            $this->line(sprintf(
                '%s #%d  %s  opened %s  → closing at %s',
                $dry ? '[dry-run]' : 'closing ',
                $session->id,
                $session->employee?->name ?? 'unknown',
                $session->clock_in->format('d M Y H:i'),
                $closeAt->format('d M Y H:i'),
            ));

            if (! $dry) {
                $session->forceFill([
                    'clock_out' => $closeAt,
                    'closed_by' => Attendance::CLOSED_AUTO,
                    'correction_note' => 'Closed automatically — no clock-out was recorded. Please check with HR.',
                ])->saveQuietly();
            }
        }

        $this->newLine();
        $this->info(($dry ? 'Would close ' : 'Closed ') . $stale->count() . ' session(s).');

        return self::SUCCESS;
    }
}

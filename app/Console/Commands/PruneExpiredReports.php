<?php

namespace App\Console\Commands;

use App\Enums\ReportExportStatus;
use App\Models\ReportExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes expired report files + their tracking rows together (EPIC-14).
 * Required by ADR-002's small-server storage constraint — generated PDFs
 * with embedded photo links could otherwise grow storage/app/reports/
 * unbounded.
 */
class PruneExpiredReports extends Command
{
    protected $signature = 'reports:prune';

    protected $description = 'Delete expired generated report files (EPIC-14)';

    public function handle(): int
    {
        $deleted = 0;

        ReportExport::where('status', ReportExportStatus::Ready->value)
            ->where('expires_at', '<', now())
            ->chunkById(100, function ($batch) use (&$deleted) {
                foreach ($batch as $export) {
                    if (is_string($export->path)) {
                        Storage::disk($export->disk)->delete($export->path);
                    }

                    $export->delete();
                    $deleted++;
                }
            });

        $this->info("Report pruning complete: {$deleted} expired report(s) deleted.");

        return self::SUCCESS;
    }
}

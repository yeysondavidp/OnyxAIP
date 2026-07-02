<?php

namespace App\Jobs;

use App\Enums\ReportExportStatus;
use App\Models\ReportExport;
use App\Services\Reports\ReportGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Queued path for large report exports (EPIC-14 — "queued for large datasets
 * with a download made available when ready"). ReportService dispatches this
 * only when the row count exceeds its threshold; smaller reports run inline
 * via the same ReportGenerator, so there is exactly one generation code path.
 */
class GenerateReportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly int $reportExportId) {}

    public function handle(ReportGenerator $generator): void
    {
        $export = ReportExport::findOrFail($this->reportExportId);
        $export->update(['status' => ReportExportStatus::Processing->value]);

        $generator->generate($export, notifyOnCompletion: true);
    }

    public function failed(\Throwable $e): void
    {
        ReportExport::where('id', $this->reportExportId)->update([
            'status'         => ReportExportStatus::Failed->value,
            'failure_reason' => $e->getMessage(),
        ]);

        WriteAuditLog::dispatch(
            userId: null,
            userRole: null,
            action: 'report.generation_failed',
            auditableType: ReportExport::class,
            auditableId: $this->reportExportId,
            before: null,
            after: ['reason' => $e->getMessage()],
            ipAddress: null,
            userAgent: null,
        );
    }
}

<?php

namespace App\Services\Reports;

use App\Enums\ReportExportStatus;
use App\Enums\ReportFormat;
use App\Enums\ReportType;
use App\Jobs\GenerateReportJob;
use App\Models\ReportExport;
use App\Services\Reports\Builders\ReportBuilder;

/**
 * The single entry point every EPIC-14 report controller calls. Owns the
 * sync-vs-queue decision (made once here, not duplicated per report) and the
 * client_id scoping contract: callers pass an already-validated/authorised
 * $clientId (never a raw request value) which is stored on the ReportExport
 * row and is what every builder must filter its queries by explicitly —
 * not the ClientScope global scope, which is a no-op outside a request
 * context (e.g. inside the queued GenerateReportJob).
 */
class ReportService
{
    /** Above this row count, generation is queued instead of run inline. */
    private const QUEUE_THRESHOLD = 200;

    /**
     * @param  array<string, mixed>  $params  validated request params, stored verbatim
     *                                        for the report header/audit trail and
     *                                        re-read by the builder at generation time
     */
    public function run(
        ReportType $type,
        ReportFormat $format,
        ?int $clientId,
        int $actorUserId,
        array $params,
        ReportBuilder $builder,
    ): ReportExport {
        $rowCount = (clone $builder->countQuery($params, $clientId))->count();
        $queued   = $rowCount > self::QUEUE_THRESHOLD;

        $export = ReportExport::create([
            'report_type'          => $type->value,
            'format'               => $format->value,
            'client_id'            => $clientId,
            'requested_by_user_id' => $actorUserId,
            'params'               => $params,
            'status'               => $queued ? ReportExportStatus::Queued->value : ReportExportStatus::Processing->value,
            'disk'                 => 'local',
            'expires_at'           => now()->addHours(72),
        ]);

        if ($queued) {
            GenerateReportJob::dispatch($export->id);
        } else {
            app(ReportGenerator::class)->generate($export);
        }

        return $export->fresh();
    }
}

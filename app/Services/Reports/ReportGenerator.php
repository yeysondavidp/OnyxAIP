<?php

namespace App\Services\Reports;

use App\Enums\ReportExportStatus;
use App\Enums\ReportFormat;
use App\Jobs\WriteAuditLog;
use App\Models\ReportExport;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Reports\Builders\AssetRegisterReportBuilder;
use App\Services\Reports\Builders\AssetStatusSummaryReportBuilder;
use App\Services\Reports\Builders\DisplayGroupTopologyReportBuilder;
use App\Services\Reports\Builders\OpenFaultsReportBuilder;
use App\Services\Reports\Builders\ReportBuilder;
use App\Services\Reports\Builders\ServiceHistoryAssetReportBuilder;
use App\Services\Reports\Builders\ServiceHistoryStoreReportBuilder;
use App\Services\Reports\Builders\SlaComplianceReportBuilder;
use App\Services\Reports\Builders\TechnicianHoursReportBuilder;
use App\Services\Reports\Builders\WarrantyExpiryForecastReportBuilder;
use Illuminate\Support\Str;

/**
 * The single "generate → store → sign → audit → notify" implementation both
 * the sync path (ReportService::run) and the queued path (GenerateReportJob)
 * converge on — one code path, not two (Engineering Bar — Clean).
 */
class ReportGenerator
{
    /** @var array<string, class-string<ReportBuilder>> */
    private const BUILDERS = [
        'asset_register'           => AssetRegisterReportBuilder::class,
        'asset_status_summary'     => AssetStatusSummaryReportBuilder::class,
        'service_history_asset'    => ServiceHistoryAssetReportBuilder::class,
        'service_history_store'    => ServiceHistoryStoreReportBuilder::class,
        'open_faults'              => OpenFaultsReportBuilder::class,
        'sla_compliance'           => SlaComplianceReportBuilder::class,
        'technician_hours'         => TechnicianHoursReportBuilder::class,
        'warranty_expiry_forecast' => WarrantyExpiryForecastReportBuilder::class,
        'display_group_topology'   => DisplayGroupTopologyReportBuilder::class,
    ];

    public function __construct(private readonly NotificationDispatcher $notifications) {}

    public function generate(ReportExport $export, bool $notifyOnCompletion = false): void
    {
        $builder = app(self::BUILDERS[$export->report_type->value]);
        $path    = $export->report_type->value.'/'.Str::uuid().'.'.$export->format->value;

        $rowCount = $export->format === ReportFormat::Csv
            ? $builder->writeCsv($export, $path)
            : $builder->writePdf($export, $path);

        $export->update([
            'status'    => ReportExportStatus::Ready->value,
            'path'      => $path,
            'row_count' => $rowCount,
        ]);

        $actor = User::find($export->requested_by_user_id);

        WriteAuditLog::dispatch(
            userId: $export->requested_by_user_id,
            userRole: $actor?->role?->value,
            action: 'report.generated',
            auditableType: ReportExport::class,
            auditableId: $export->id,
            before: null,
            after: [
                'report_type' => $export->report_type->value,
                'format'      => $export->format->value,
                'client_id'   => $export->client_id,
                'row_count'   => $rowCount,
                'params'      => $export->params,
            ],
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
        );

        if ($notifyOnCompletion && $actor !== null) {
            $this->notifications->reportReady($export->fresh(), $actor);
        }
    }
}

<?php

namespace App\Services\Reports\Builders;

use App\Models\ReportExport;
use App\Models\ServiceJob;
use App\Services\Reports\CsvExportWriter;
use Illuminate\Database\Eloquent\Builder;

/**
 * US-14.3 — SLA Compliance (CSV): reads the EPIC-12 stored SLA state
 * read-only (ServiceJob::slaStatus(), sla_at_risk, sla_breached, the clock
 * timestamps) — zero recomputation, per the AC. Only the resolution clock is
 * actually tracked (no acknowledgement/on-site-response actual timestamps
 * exist), so those SlaProfile hours are surfaced as targets, not measurements.
 */
class SlaComplianceReportBuilder implements ReportBuilder
{
    public function __construct(private readonly CsvExportWriter $csv) {}

    public function countQuery(array $params, ?int $clientId): Builder
    {
        return $this->query($params, $clientId);
    }

    public function writeCsv(ReportExport $export, string $path): int
    {
        $query = $this->query($export->params, $export->client_id)->with(['store', 'slaProfile']);

        return $this->csv->streamToDisk('local', $path, [
            'Job Reference', 'Store', 'Job Type', 'Job Status',
            'Acknowledgement Target (hrs)', 'On-site Response Target (hrs)', 'Resolution Target (hrs)',
            'SLA Clock Started', 'Resolution Target At', 'SLA Status', 'Breached',
        ], $query, fn (ServiceJob $job) => [
            $job->job_reference,
            $job->store->store_name,
            $job->job_type->label(),
            $job->job_status->label(),
            (string) $job->slaProfile->acknowledgement_hours,
            (string) $job->slaProfile->onsite_response_metro_hours,
            (string) $job->slaProfile->resolution_hours,
            $job->sla_clock_started_at?->format('Y-m-d H:i')     ?? '',
            $job->sla_resolution_target_at?->format('Y-m-d H:i') ?? '',
            $job->slaStatus()->label(),
            $job->sla_breached ? 'Y' : 'N',
        ]);
    }

    public function writePdf(ReportExport $export, string $path): int
    {
        throw new \LogicException('SLA Compliance is CSV-only.');
    }

    /** @return Builder<ServiceJob> */
    private function query(array $params, ?int $clientId): Builder
    {
        return ServiceJob::allClients()
            ->where('client_id', $clientId)
            ->whereNotNull('sla_profile_id')
            ->whereBetween('scheduled_date', [$params['date_from'], $params['date_to']])
            ->orderBy('scheduled_date');
    }
}

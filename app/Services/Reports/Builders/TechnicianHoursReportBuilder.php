<?php

namespace App\Services\Reports\Builders;

use App\Models\JobCheckpoint;
use App\Models\ReportExport;
use App\Models\TechnicianProfile;
use App\Services\Reports\CsvExportWriter;
use App\Support\DurationCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * US-14.4 — Technician Hours (CSV): no client dimension (PM-only). Hours are
 * derived from job_checkpoints (no precomputed duration exists anywhere) via
 * the shared DurationCalculator, with per-technician subtotals accumulated
 * during the chunked pass and written as a trailing TOTAL section.
 */
class TechnicianHoursReportBuilder implements ReportBuilder
{
    public function __construct(private readonly CsvExportWriter $csv) {}

    public function countQuery(array $params, ?int $clientId): Builder
    {
        return $this->query($params);
    }

    public function writeCsv(ReportExport $export, string $path): int
    {
        $params     = $export->params;
        $query      = $this->query($params)->with('profile');
        $subtotals  = []; // technician_profile_id => float hours
        $detailRows = [];

        $query->chunk(500, function (Collection $checkpoints) use (&$detailRows, &$subtotals) {
            foreach ($checkpoints as $checkpoint) {
                if ($checkpoint->start_timestamp_utc === null || $checkpoint->end_timestamp_utc === null) {
                    continue; // whereNotNull('end_timestamp_utc') already excludes these; defensive only
                }

                $hours = DurationCalculator::hours($checkpoint->start_timestamp_utc, $checkpoint->end_timestamp_utc);
                $name  = $checkpoint->profile->name;

                $detailRows[] = [
                    $name,
                    $checkpoint->job_id,
                    $checkpoint->start_timestamp_utc->format('Y-m-d H:i'),
                    $checkpoint->end_timestamp_utc->format('Y-m-d H:i'),
                    number_format($hours, 2),
                ];

                $subtotals[$checkpoint->technician_profile_id] = ($subtotals[$checkpoint->technician_profile_id] ?? 0) + $hours;
            }
        });

        $rowCount = count($detailRows);

        $technicianNames = TechnicianProfile::whereIn('id', array_keys($subtotals))->pluck('name', 'id');

        $totalRows   = [['', '', '', '', '']];
        $totalRows[] = ['TOTAL BY TECHNICIAN', '', '', '', ''];
        foreach ($subtotals as $technicianId => $hours) {
            $totalRows[] = [$technicianNames->get($technicianId, 'Unknown'), '', '', '', number_format($hours, 2)];
        }

        $this->csv->writeRowsToDisk('local', $path, [
            'Technician', 'Job ID', 'Started (UTC)', 'Ended (UTC)', 'Hours',
        ], [...$detailRows, ...$totalRows]);

        return $rowCount;
    }

    public function writePdf(ReportExport $export, string $path): int
    {
        throw new \LogicException('Technician Hours is CSV-only.');
    }

    /** @return Builder<JobCheckpoint> */
    private function query(array $params): Builder
    {
        return JobCheckpoint::query()
            ->whereNotNull('end_timestamp_utc')
            ->whereBetween('start_timestamp_utc', [$params['date_from'], $params['date_to']])
            ->when($params['technician_profile_id'] ?? null, fn (Builder $q, $id) => $q->where('technician_profile_id', $id))
            ->orderBy('start_timestamp_utc');
    }
}

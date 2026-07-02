<?php

namespace App\Services\Reports\Builders;

use App\Enums\AssetStatus;
use App\Enums\JobStatus;
use App\Models\Asset;
use App\Models\ReportExport;
use App\Models\ServiceHistory;
use App\Services\Reports\CsvExportWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * US-14.3 — Open Faults (CSV): every asset currently Faulty/Offline, with
 * last service date and any open job reference resolved via correlated
 * subqueries (no per-row queries in the chunk callback — Engineering Bar).
 */
class OpenFaultsReportBuilder implements ReportBuilder
{
    public function __construct(private readonly CsvExportWriter $csv) {}

    public function countQuery(array $params, ?int $clientId): Builder
    {
        return $this->baseQuery($params, $clientId);
    }

    public function writeCsv(ReportExport $export, string $path): int
    {
        $query = $this->query($export->params, $export->client_id)->with('store');

        return $this->csv->streamToDisk('local', $path, [
            'Asset Code', 'Asset Name', 'Store', 'State', 'Status', 'Last Service Date', 'Open Job Reference',
        ], $query, fn (Asset $a) => [
            $a->asset_code,
            $a->asset_name,
            $a->store?->store_name,
            $a->store?->state->value,
            $a->asset_status->label(),
            $a->last_service_date,
            $a->open_job_reference,
        ]);
    }

    public function writePdf(ReportExport $export, string $path): int
    {
        throw new \LogicException('Open Faults is CSV-only.');
    }

    /** @return Builder<Asset> */
    private function baseQuery(array $params, ?int $clientId): Builder
    {
        return Asset::allClients()
            ->where('client_id', $clientId)
            ->whereIn('asset_status', [AssetStatus::Faulty->value, AssetStatus::Offline->value])
            ->when($params['state'] ?? null, fn (Builder $q, $state) => $q->whereHas('store', fn (Builder $s) => $s->where('state', $state)));
    }

    /** @return Builder<Asset> */
    private function query(array $params, ?int $clientId): Builder
    {
        $lastService = ServiceHistory::query()
            ->select('service_date')
            ->whereColumn('service_history.asset_id', 'assets.id')
            ->orderByDesc('service_date')
            ->limit(1);

        $openJobReference = DB::table('job_assets')
            ->join('service_jobs', 'service_jobs.id', '=', 'job_assets.job_id')
            ->whereColumn('job_assets.asset_id', 'assets.id')
            ->whereNull('service_jobs.deleted_at')
            ->whereNotIn('service_jobs.job_status', [JobStatus::Validated->value, JobStatus::Cancelled->value])
            ->orderByDesc('service_jobs.created_at')
            ->limit(1)
            ->select('service_jobs.job_reference');

        return $this->baseQuery($params, $clientId)
            ->addSelect('assets.*')
            ->addSelect(['last_service_date' => $lastService])
            ->addSelect(['open_job_reference' => $openJobReference])
            ->orderBy('asset_code');
    }
}

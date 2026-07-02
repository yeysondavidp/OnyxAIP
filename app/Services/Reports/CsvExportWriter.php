<?php

namespace App\Services\Reports;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * The single CSV writer reused by every EPIC-14 report (Engineering Bar —
 * Clean). No CSV package is used — native fputcsv, matching the approach
 * documented for EPIC-15's still-unbuilt CSV export.
 */
class CsvExportWriter
{
    /**
     * @param  list<string>  $headers
     * @param  Builder<*>  $query  already filtered/scoped/eager-loaded
     * @param  Closure(mixed): list<string>  $rowMapper
     * @return int rows written (excluding the header row)
     */
    public function streamToDisk(
        string $disk,
        string $path,
        array $headers,
        Builder $query,
        Closure $rowMapper,
        int $chunkSize = 500,
    ): int {
        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, $headers);

        $rowCount = 0;

        $query->chunk($chunkSize, function (Collection $rows) use ($handle, $rowMapper, &$rowCount) {
            foreach ($rows as $row) {
                fputcsv($handle, $rowMapper($row));
                $rowCount++;
            }
        });

        rewind($handle);
        Storage::disk($disk)->put($path, stream_get_contents($handle));
        fclose($handle);

        return $rowCount;
    }

    /**
     * Writes pre-built rows directly (no query to chunk) — used by aggregation
     * reports (e.g. Asset Status Summary) and reports that need a trailing
     * subtotal section (e.g. Technician Hours) where rows are accumulated in
     * PHP rather than streamed straight from a Builder.
     *
     * @param  list<string>  $headers
     * @param  iterable<int, list<string>>  $rows
     */
    public function writeRowsToDisk(string $disk, string $path, array $headers, iterable $rows): int
    {
        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, $headers);

        $rowCount = 0;

        foreach ($rows as $row) {
            fputcsv($handle, $row);
            $rowCount++;
        }

        rewind($handle);
        Storage::disk($disk)->put($path, stream_get_contents($handle));
        fclose($handle);

        return $rowCount;
    }
}

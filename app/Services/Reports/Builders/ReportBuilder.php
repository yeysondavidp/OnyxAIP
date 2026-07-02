<?php

namespace App\Services\Reports\Builders;

use App\Models\ReportExport;
use Illuminate\Database\Eloquent\Builder;

/**
 * One implementation per ReportType (App\Services\Reports\ReportGenerator
 * dispatches to these). A builder that doesn't support a given format throws
 * LogicException from that method — the controller only ever requests a
 * format the report actually offers (enforced by its Form Request).
 */
interface ReportBuilder
{
    /**
     * Cheap COUNT(*) query with the same filters the real report will use —
     * built from the controller's already-validated/authorised params, before
     * a ReportExport row exists, so ReportService can decide sync vs queued.
     *
     * @param  array<string, mixed>  $params
     */
    public function countQuery(array $params, ?int $clientId): Builder;

    /** @return int rows written */
    public function writeCsv(ReportExport $export, string $path): int;

    /** @return int rows written */
    public function writePdf(ReportExport $export, string $path): int;
}

<?php

namespace App\Enums;

/**
 * One case per report in EPIC-14 (SRA §13). The value is also the report's
 * storage sub-directory (storage/app/reports/{value}/...).
 */
enum ReportType: string
{
    case AssetRegister          = 'asset_register';
    case AssetStatusSummary     = 'asset_status_summary';
    case ServiceHistoryAsset    = 'service_history_asset';
    case ServiceHistoryStore    = 'service_history_store';
    case OpenFaults             = 'open_faults';
    case SlaCompliance          = 'sla_compliance';
    case TechnicianHours        = 'technician_hours';
    case WarrantyExpiryForecast = 'warranty_expiry_forecast';
    case DisplayGroupTopology   = 'display_group_topology';

    public function label(): string
    {
        return match ($this) {
            self::AssetRegister          => 'Asset Register',
            self::AssetStatusSummary     => 'Asset Status Summary',
            self::ServiceHistoryAsset    => 'Service History — Asset',
            self::ServiceHistoryStore    => 'Service History — Store',
            self::OpenFaults             => 'Open Faults',
            self::SlaCompliance          => 'SLA Compliance',
            self::TechnicianHours        => 'Technician Hours',
            self::WarrantyExpiryForecast => 'Warranty Expiry Forecast',
            self::DisplayGroupTopology   => 'Display Group Topology',
        };
    }
}

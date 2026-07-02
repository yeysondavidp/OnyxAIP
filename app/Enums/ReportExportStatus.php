<?php

namespace App\Enums;

enum ReportExportStatus: string
{
    case Queued     = 'queued';
    case Processing = 'processing';
    case Ready      = 'ready';
    case Failed     = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued     => 'Queued',
            self::Processing => 'Processing',
            self::Ready      => 'Ready',
            self::Failed     => 'Failed',
        };
    }
}

<?php

namespace App\Enums;

enum ContentChangeFrequency: string
{
    case Static        = 'static';
    case Weekly        = 'weekly';
    case Monthly       = 'monthly';
    case CampaignBased = 'campaign_based';

    public function label(): string
    {
        return match ($this) {
            self::Static        => 'Static',
            self::Weekly        => 'Weekly',
            self::Monthly       => 'Monthly',
            self::CampaignBased => 'Campaign-based',
        };
    }
}

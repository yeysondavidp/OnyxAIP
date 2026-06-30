<?php

namespace App\Enums;

enum TechnicianSpecialty: string
{
    case AvInstallation      = 'av_installation';
    case DigitalSignage      = 'digital_signage';
    case Electrical          = 'electrical';
    case RetailFitout        = 'retail_fitout';
    case LightboxService     = 'lightbox_service';
    case NetworkConnectivity = 'network_connectivity';

    public function label(): string
    {
        return match ($this) {
            self::AvInstallation      => 'AV Installation',
            self::DigitalSignage      => 'Digital Signage',
            self::Electrical          => 'Electrical',
            self::RetailFitout        => 'Retail Fit-out',
            self::LightboxService     => 'Lightbox Service',
            self::NetworkConnectivity => 'Network/Connectivity',
        };
    }
}

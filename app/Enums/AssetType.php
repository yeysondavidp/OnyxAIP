<?php

namespace App\Enums;

enum AssetType: string
{
    case DigitalScreen  = 'digital_screen';
    case MediaPlayer    = 'media_player';
    case Lightbox       = 'lightbox';
    case WindowFixture  = 'window_fixture';
    case Infrastructure = 'infrastructure';

    public function label(): string
    {
        return match ($this) {
            self::DigitalScreen  => 'Digital Screen',
            self::MediaPlayer    => 'Media Player',
            self::Lightbox       => 'Lightbox',
            self::WindowFixture  => 'Window Fixture',
            self::Infrastructure => 'Infrastructure',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::DigitalScreen  => 'monitor',
            self::MediaPlayer    => 'cpu',
            self::Lightbox       => 'sun',
            self::WindowFixture  => 'layout',
            self::Infrastructure => 'tool',
        };
    }
}

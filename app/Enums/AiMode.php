<?php

namespace App\Enums;

/** Per-client AI setting (ADR-003 §6, D8). Off by default: nothing leaves ONYX until a PM opts in. */
enum AiMode: string
{
    case Off          = 'off';
    case LabelCrops   = 'label_crops';
    case PublicPhotos = 'public_photos';

    public function label(): string
    {
        return match ($this) {
            self::Off          => 'Off — no AI',
            self::LabelCrops   => 'Label crops only',
            self::PublicPhotos => 'Label crops and public-area photos',
        };
    }

    public function allows(AiDataCategory $category): bool
    {
        return match ($this) {
            self::Off          => false,
            self::LabelCrops   => $category !== AiDataCategory::PublicPhoto,
            self::PublicPhotos => true,
        };
    }
}

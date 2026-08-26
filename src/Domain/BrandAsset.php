<?php

namespace App\Domain;

enum BrandAsset: string
{
    case LOGO = 'logo';
    case FAVICON = 'favicon';
    case OG_IMAGE = 'og_image';
    case HERO = 'hero';
    case PRESENTATION = 'preparation';

    /** @return list<string> */
    public static function storageKeys(): array
    {
        return array_map(static fn(self $asset): string => $asset->value, self::cases());
    }
}

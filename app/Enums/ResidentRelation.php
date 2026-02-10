<?php

namespace App\Enums;

enum ResidentRelation: string
{
    case OWNER = 'owner';
    case SPOUSE = 'spouse';
    case SON = 'son';
    case DAUGHTER = 'daughter';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Owner',
            self::SPOUSE => 'Spouse',
            self::SON => 'Son',
            self::DAUGHTER => 'Daughter',
            self::OTHER => 'Other',
        };
    }
}

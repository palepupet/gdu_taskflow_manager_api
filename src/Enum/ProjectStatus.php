<?php

declare(strict_types=1);

namespace App\Enum;

enum ProjectStatus: string
{
    case IN_PROGRESS = 'en cours';
    case COMPLETED = 'terminé';
    case CANCELLED = 'annulé';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status) => $status->value, self::cases());
    }
}

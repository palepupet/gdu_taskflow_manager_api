<?php

declare(strict_types=1);

namespace App\Enum;

enum TaskState: string
{
    case OPEN = 'ouvert';
    case IN_PROGRESS = 'en cours';
    case CLOSED = 'terminé';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $state) => $state->value, self::cases());
    }
}

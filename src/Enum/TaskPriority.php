<?php

declare(strict_types=1);

namespace App\Enum;

enum TaskPriority: string
{
    case LOW = 'basse';
    case MEDIUM = 'moyenne';
    case HIGH = 'élevée';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $priority) => $priority->value, self::cases());
    }
}

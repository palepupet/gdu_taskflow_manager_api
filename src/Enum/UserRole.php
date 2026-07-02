<?php

declare(strict_types=1);

namespace App\Enum;

enum UserRole: string
{
    case User = 'ROLE_USER';
    case Manager = 'ROLE_MANAGER';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $role) => $role->value, self::cases());
    }
}

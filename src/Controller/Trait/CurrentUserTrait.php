<?php

declare(strict_types=1);

namespace App\Controller\Trait;

use App\Entity\User;

trait CurrentUserTrait
{
    private function getCurrentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}

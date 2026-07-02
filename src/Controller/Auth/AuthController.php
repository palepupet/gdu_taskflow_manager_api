<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use Symfony\Component\Routing\Attribute\Route;

class AuthController
{
    #[Route('/auth/login', name:'auth_login', methods: ['POST'])]
    public function login(): void
    {
    }
}

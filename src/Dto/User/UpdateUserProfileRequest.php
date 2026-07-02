<?php

declare(strict_types=1);

namespace App\Dto\User;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateUserProfileRequest
{
    public function __construct(
        #[Assert\Type('string', message: 'Le prénom doit être une chaîne de caractères.')]
        #[Assert\NotBlank(message: 'Le prénom ne peut pas être vide.', allowNull: true)]
        public mixed $firstName = null,
        #[Assert\Type('string', message: 'Le nom doit être une chaîne de caractères.')]
        #[Assert\NotBlank(message: 'Le nom ne peut pas être vide.', allowNull: true)]
        public mixed $lastName = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            firstName: $data['firstName'] ?? null,
            lastName: $data['lastName'] ?? null,
        );
    }
}

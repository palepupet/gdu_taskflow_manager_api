<?php

declare(strict_types=1);

namespace App\Dto\User;

use App\Enum\UserRole;
use Symfony\Component\Validator\Constraints as Assert;

class UpdateUserRequest
{
    /**
     * @param array<string>|null $roles
     */
    public function __construct(
        #[Assert\NotBlank(message: 'Le prénom ne peut pas être vide.', allowNull: true)]
        #[Assert\Length(max: 30)]
        public ?string $firstName = null,
        #[Assert\NotBlank(message: 'Le nom ne peut pas être vide.', allowNull: true)]
        #[Assert\Length(max: 30)]
        public ?string $lastName = null,
        #[Assert\NotBlank(message: 'L\'email ne peut pas être vide.', allowNull: true)]
        #[Assert\Email(message: 'L\'email n\'est pas valide.')]
        public ?string $email = null,
        #[Assert\All([
            new Assert\Choice(callback: [UserRole::class, 'values']),
        ])]
        public ?array $roles = null,
        #[Assert\Type('bool')]
        public ?bool $isActive = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $roles = null;
        if (isset($data['roles']) && \is_array($data['roles'])) {
            $roles = array_values(array_filter($data['roles'], '\is_string'));
        }

        return new self(
            firstName: isset($data['firstName']) && \is_string($data['firstName']) ? $data['firstName'] : null,
            lastName: isset($data['lastName']) && \is_string($data['lastName']) ? $data['lastName'] : null,
            email: isset($data['email']) && \is_string($data['email']) ? $data['email'] : null,
            roles: $roles,
            isActive: isset($data['isActive']) && \is_bool($data['isActive']) ? $data['isActive'] : null,
        );
    }
}

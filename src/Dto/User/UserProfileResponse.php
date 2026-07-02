<?php

declare(strict_types=1);

namespace App\Dto\User;

use App\Entity\User;

class UserProfileResponse
{
    /**
     * @param array<string> $roles
     */
    public function __construct(
        public int $id,
        public string $email,
        public string $firstName,
        public string $lastName,
        public array $roles,
        public bool $isActive,
        public ?string $createdAt,
    ) {
    }

    public static function fromUser(User $user): self
    {
        $id = $user->getId();
        if (null === $id) {
            throw new \LogicException('Un utilisateur persisté doit avoir un identifiant.');
        }

        return new self(
            id: $id,
            email: (string) $user->getEmail(),
            firstName: (string) $user->getFirstName(),
            lastName: (string) $user->getLastName(),
            roles: $user->getRoles(),
            isActive: $user->isActive(),
            createdAt: $user->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'roles' => $this->roles,
            'isActive' => $this->isActive,
            'createdAt' => $this->createdAt,
        ];
    }
}

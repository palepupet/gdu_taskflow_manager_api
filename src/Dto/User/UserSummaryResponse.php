<?php

declare(strict_types=1);

namespace App\Dto\User;

use App\Entity\User;

class UserSummaryResponse
{
    public function __construct(
        public int $id,
        public string $firstName,
        public string $lastName,
        public string $email,
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
            firstName: (string) $user->getFirstName(),
            lastName: (string) $user->getLastName(),
            email: (string) $user->getEmail(),
        );
    }

    /**
     * @return array{
     *     id: int,
     *     email: string,
     *     firstName: string,
     *     lastName: string
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
        ];
    }
}
